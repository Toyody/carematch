resource "aws_ecr_repository" "backend" {
  name                 = "${local.name}-backend"
  image_tag_mutability = "IMMUTABLE"
  image_scanning_configuration { scan_on_push = true }
}

resource "aws_ecr_repository" "frontend" {
  name                 = "${local.name}-frontend"
  image_tag_mutability = "IMMUTABLE"
  image_scanning_configuration { scan_on_push = true }
}

resource "aws_ecs_cluster" "main" {
  name = local.name
  setting {
    name  = "containerInsights"
    value = "enabled"
  }
}

resource "aws_cloudwatch_log_group" "backend" {
  name              = "/ecs/${local.name}/backend"
  retention_in_days = 30
}

resource "aws_cloudwatch_log_group" "frontend" {
  name              = "/ecs/${local.name}/frontend"
  retention_in_days = 30
}

resource "aws_cloudwatch_log_group" "worker" {
  name              = "/ecs/${local.name}/worker"
  retention_in_days = 30
}

resource "aws_lb" "main" {
  name               = substr(local.name, 0, 32)
  load_balancer_type = "application"
  internal           = false
  security_groups    = [aws_security_group.alb.id]
  subnets            = aws_subnet.public[*].id
}

resource "aws_lb_target_group" "backend" {
  name        = substr("${local.name}-backend", 0, 32)
  port        = 8000
  protocol    = "HTTP"
  target_type = "ip"
  vpc_id      = aws_vpc.main.id
  health_check {
    path    = "/api/v1/health"
    matcher = "200"
  }
}

resource "aws_lb_target_group" "frontend" {
  name        = substr("${local.name}-frontend", 0, 32)
  port        = 3000
  protocol    = "HTTP"
  target_type = "ip"
  vpc_id      = aws_vpc.main.id
  health_check {
    path    = "/"
    matcher = "200-399"
  }
}

resource "aws_lb_listener" "https" {
  load_balancer_arn = aws_lb.main.arn
  port              = 443
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS13-1-2-2021-06"
  certificate_arn   = var.acm_certificate_arn
  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.frontend.arn
  }
}

resource "aws_lb_listener_rule" "backend" {
  listener_arn = aws_lb_listener.https.arn
  priority     = 10
  action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.backend.arn
  }
  condition {
    path_pattern {
      values = ["/api/*", "/sanctum/*"]
    }
  }
}

locals {
  common_backend_environment = [
    { name = "APP_ENV", value = "production" },
    { name = "APP_DEBUG", value = "false" },
    { name = "APP_URL", value = var.application_url },
    { name = "FRONTEND_URL", value = var.application_url },
    { name = "LOG_CHANNEL", value = "stderr" },
    { name = "DB_CONNECTION", value = "pgsql" },
    { name = "DB_HOST", value = aws_db_instance.postgres.address },
    { name = "DB_PORT", value = tostring(aws_db_instance.postgres.port) },
    { name = "DB_DATABASE", value = var.database_name },
    { name = "DB_USERNAME", value = var.database_username },
    { name = "DB_SSLMODE", value = "require" },
    { name = "SESSION_DRIVER", value = "database" },
    { name = "SESSION_SECURE_COOKIE", value = "true" },
    { name = "SESSION_HTTP_ONLY", value = "true" },
    { name = "SESSION_SAME_SITE", value = "lax" },
    { name = "CACHE_STORE", value = "redis" },
    { name = "REDIS_CLIENT", value = "phpredis" },
    { name = "REDIS_HOST", value = aws_elasticache_replication_group.redis.primary_endpoint_address },
    { name = "REDIS_PORT", value = "6379" },
    { name = "REDIS_SCHEME", value = "tls" },
    { name = "QUEUE_CONNECTION", value = "sqs" },
    { name = "QUEUE_FAILED_DRIVER", value = "null" },
    { name = "SQS_PREFIX", value = "https://sqs.${data.aws_region.current.region}.amazonaws.com/${data.aws_caller_identity.current.account_id}" },
    { name = "SQS_QUEUE", value = aws_sqs_queue.expiry_digest.name },
    { name = "COMPLIANCE_EXPIRY_DIGEST_QUEUE", value = aws_sqs_queue.expiry_digest.name },
    { name = "AWS_DEFAULT_REGION", value = data.aws_region.current.region },
    { name = "CANDIDATE_DOCUMENTS_DISK", value = "candidate_documents_s3" },
    { name = "AWS_CANDIDATE_DOCUMENTS_BUCKET", value = aws_s3_bucket.candidate_documents.id },
    { name = "MAIL_MAILER", value = var.mail_mailer },
    { name = "MAIL_HOST", value = var.mail_host },
    { name = "MAIL_PORT", value = tostring(var.mail_port) },
    { name = "MAIL_USERNAME", value = var.mail_username },
    { name = "MAIL_SCHEME", value = "tls" },
    { name = "MAIL_FROM_ADDRESS", value = var.mail_from_address },
    { name = "CARE_MATCH_PUBLIC_DEMO", value = "false" },
  ]

  backend_secrets = [
    { name = "APP_KEY", valueFrom = var.app_key_secret_arn },
    { name = "DB_PASSWORD", valueFrom = "${aws_db_instance.postgres.master_user_secret[0].secret_arn}:password::" },
    { name = "REDIS_PASSWORD", valueFrom = var.redis_auth_token_secret_arn },
    { name = "MAIL_PASSWORD", valueFrom = var.mail_password_secret_arn },
  ]
}

resource "aws_ecs_task_definition" "backend" {
  family                   = "${local.name}-backend"
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_execution.arn
  task_role_arn            = aws_iam_role.backend_task.arn
  container_definitions = jsonencode([{
    name             = "backend", image = var.backend_image, essential = true,
    portMappings     = [{ containerPort = 8000, protocol = "tcp" }],
    environment      = local.common_backend_environment,
    secrets          = local.backend_secrets,
    logConfiguration = { logDriver = "awslogs", options = { awslogs-group = aws_cloudwatch_log_group.backend.name, awslogs-region = data.aws_region.current.region, awslogs-stream-prefix = "backend" } }
  }])
}

resource "aws_ecs_task_definition" "frontend" {
  family                   = "${local.name}-frontend"
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = 256
  memory                   = 512
  execution_role_arn       = aws_iam_role.ecs_execution.arn
  container_definitions = jsonencode([{
    name             = "frontend", image = var.frontend_image, essential = true,
    portMappings     = [{ containerPort = 3000, protocol = "tcp" }],
    environment      = [{ name = "NEXT_PUBLIC_API_URL", value = "" }],
    logConfiguration = { logDriver = "awslogs", options = { awslogs-group = aws_cloudwatch_log_group.frontend.name, awslogs-region = data.aws_region.current.region, awslogs-stream-prefix = "frontend" } }
  }])
}

resource "aws_ecs_task_definition" "worker" {
  family                   = "${local.name}-worker"
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = 256
  memory                   = 512
  execution_role_arn       = aws_iam_role.ecs_execution.arn
  task_role_arn            = aws_iam_role.worker_task.arn
  container_definitions = jsonencode([{
    name             = "worker", image = var.backend_image, essential = true,
    command          = ["php", "artisan", "queue:work", "sqs", "--queue=${aws_sqs_queue.expiry_digest.name}", "--tries=0", "--timeout=60", "--sleep=1", "--max-time=3600", "--memory=256"],
    stopTimeout      = 120,
    environment      = local.common_backend_environment,
    secrets          = local.backend_secrets,
    logConfiguration = { logDriver = "awslogs", options = { awslogs-group = aws_cloudwatch_log_group.worker.name, awslogs-region = data.aws_region.current.region, awslogs-stream-prefix = "worker" } }
  }])
}

resource "aws_ecs_service" "backend" {
  name            = "backend"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.backend.arn
  desired_count   = 1
  launch_type     = "FARGATE"
  network_configuration {
    subnets          = aws_subnet.public[*].id
    security_groups  = [aws_security_group.ecs.id]
    assign_public_ip = true
  }
  load_balancer {
    target_group_arn = aws_lb_target_group.backend.arn
    container_name   = "backend"
    container_port   = 8000
  }
  depends_on = [aws_lb_listener.https]
}

resource "aws_ecs_service" "frontend" {
  name            = "frontend"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.frontend.arn
  desired_count   = 1
  launch_type     = "FARGATE"
  network_configuration {
    subnets          = aws_subnet.public[*].id
    security_groups  = [aws_security_group.ecs.id]
    assign_public_ip = true
  }
  load_balancer {
    target_group_arn = aws_lb_target_group.frontend.arn
    container_name   = "frontend"
    container_port   = 3000
  }
  depends_on = [aws_lb_listener.https]
}

resource "aws_ecs_service" "worker" {
  name            = "worker"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.worker.arn
  desired_count   = 1
  launch_type     = "FARGATE"
  network_configuration {
    subnets          = aws_subnet.public[*].id
    security_groups  = [aws_security_group.ecs.id]
    assign_public_ip = true
  }
}
