output "alb_dns_name" { value = aws_lb.main.dns_name }
output "backend_ecr_repository" { value = aws_ecr_repository.backend.name }
output "frontend_ecr_repository" { value = aws_ecr_repository.frontend.name }
output "ecs_cluster" { value = aws_ecs_cluster.main.name }
output "ecs_backend_service" { value = aws_ecs_service.backend.name }
output "ecs_frontend_service" { value = aws_ecs_service.frontend.name }
output "ecs_worker_service" { value = aws_ecs_service.worker.name }
output "ecs_backend_task_family" { value = aws_ecs_task_definition.backend.family }
output "ecs_frontend_task_family" { value = aws_ecs_task_definition.frontend.family }
output "ecs_worker_task_family" { value = aws_ecs_task_definition.worker.family }
output "ecs_task_subnets" { value = join(",", aws_subnet.public[*].id) }
output "ecs_task_security_groups" { value = aws_security_group.ecs.id }
output "sqs_queue_name" { value = aws_sqs_queue.expiry_digest.name }
output "sqs_dlq_name" { value = aws_sqs_queue.expiry_digest_dlq.name }
output "candidate_documents_bucket" { value = aws_s3_bucket.candidate_documents.id }
output "github_deploy_role_arn" { value = aws_iam_role.github_deploy.arn }

