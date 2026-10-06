resource "aws_db_subnet_group" "main" {
  name       = local.name
  subnet_ids = aws_subnet.private_data[*].id
}

resource "aws_db_instance" "postgres" {
  identifier                  = "${local.name}-postgres"
  engine                      = "postgres"
  engine_version              = "18"
  instance_class              = "db.t4g.micro"
  allocated_storage           = 20
  max_allocated_storage       = 100
  storage_type                = "gp3"
  storage_encrypted           = true
  db_name                     = var.database_name
  username                    = var.database_username
  manage_master_user_password = true
  multi_az                    = false
  publicly_accessible         = false
  db_subnet_group_name        = aws_db_subnet_group.main.name
  vpc_security_group_ids      = [aws_security_group.database.id]
  backup_retention_period     = 7
  deletion_protection         = var.database_deletion_protection
  skip_final_snapshot         = false
  final_snapshot_identifier   = "${local.name}-final"
  apply_immediately           = false

  lifecycle {
    prevent_destroy = true
  }
}

resource "aws_elasticache_subnet_group" "main" {
  name       = local.name
  subnet_ids = aws_subnet.private_data[*].id
}

resource "aws_elasticache_replication_group" "redis" {
  replication_group_id       = "${local.name}-cache"
  description                = "CareMatch shared cache, rate limits and distributed locks"
  engine                     = "redis"
  node_type                  = "cache.t4g.micro"
  num_cache_clusters         = 1
  port                       = 6379
  parameter_group_name       = "default.redis7"
  subnet_group_name          = aws_elasticache_subnet_group.main.name
  security_group_ids         = [aws_security_group.redis.id]
  at_rest_encryption_enabled = true
  transit_encryption_enabled = true
  auth_token                 = var.redis_auth_token
  automatic_failover_enabled = false
  multi_az_enabled           = false
  apply_immediately          = false
}

resource "aws_s3_bucket" "candidate_documents" {
  bucket_prefix = "${local.name}-candidates-"
  force_destroy = var.candidate_documents_force_destroy

  lifecycle {
    prevent_destroy = true
  }
}

resource "aws_s3_bucket_public_access_block" "candidate_documents" {
  bucket                  = aws_s3_bucket.candidate_documents.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

resource "aws_s3_bucket_versioning" "candidate_documents" {
  bucket = aws_s3_bucket.candidate_documents.id
  versioning_configuration { status = "Enabled" }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "candidate_documents" {
  bucket = aws_s3_bucket.candidate_documents.id
  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
  }
}
