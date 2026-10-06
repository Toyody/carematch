mock_provider "aws" {
  override_during = plan

  mock_data "aws_caller_identity" {
    defaults = {
      account_id = "123456789012"
      arn        = "arn:aws:iam::123456789012:root"
      id         = "123456789012"
    }
  }

  mock_data "aws_partition" {
    defaults = {
      partition          = "aws"
      dns_suffix         = "amazonaws.com"
      reverse_dns_prefix = "com.amazonaws"
    }
  }

  mock_data "aws_region" {
    defaults = {
      name   = "ap-southeast-2"
      region = "ap-southeast-2"
    }
  }
}

override_resource {
  target = aws_s3_bucket.candidate_documents
  values = {
    arn    = "arn:aws:s3:::carematch-production-candidates-test"
    bucket = "carematch-production-candidates-test"
    id     = "carematch-production-candidates-test"
  }
  override_during = plan
}

override_resource {
  target = aws_sqs_queue.expiry_digest_dlq
  values = {
    arn  = "arn:aws:sqs:ap-southeast-2:123456789012:carematch-production-compliance-dlq"
    id   = "https://sqs.ap-southeast-2.amazonaws.com/123456789012/carematch-production-compliance-dlq"
    name = "carematch-production-compliance-dlq"
  }
  override_during = plan
}

override_resource {
  target = aws_sqs_queue.expiry_digest
  values = {
    arn  = "arn:aws:sqs:ap-southeast-2:123456789012:carematch-production-compliance"
    id   = "https://sqs.ap-southeast-2.amazonaws.com/123456789012/carematch-production-compliance"
    name = "carematch-production-compliance"
  }
  override_during = plan
}

override_resource {
  target = aws_sqs_queue.ai_dlq
  values = {
    arn  = "arn:aws:sqs:ap-southeast-2:123456789012:carematch-production-ai-dlq"
    id   = "https://sqs.ap-southeast-2.amazonaws.com/123456789012/carematch-production-ai-dlq"
    name = "carematch-production-ai-dlq"
  }
  override_during = plan
}

override_resource {
  target = aws_sqs_queue.ai
  values = {
    arn  = "arn:aws:sqs:ap-southeast-2:123456789012:carematch-production-ai"
    id   = "https://sqs.ap-southeast-2.amazonaws.com/123456789012/carematch-production-ai"
    name = "carematch-production-ai"
  }
  override_during = plan
}

override_resource {
  target = aws_db_instance.postgres
  values = {
    address = "postgres.test.internal"
    port    = 5432
    master_user_secret = [{
      secret_arn = "arn:aws:secretsmanager:ap-southeast-2:123456789012:secret:carematch-database"
    }]
  }
  override_during = plan
}

override_resource {
  target = aws_elasticache_replication_group.redis
  values = {
    primary_endpoint_address = "redis.test.internal"
  }
  override_during = plan
}

override_resource {
  target          = aws_iam_role.ecs_execution
  values          = { arn = "arn:aws:iam::123456789012:role/carematch-production-ecs-execution" }
  override_during = plan
}

override_resource {
  target          = aws_iam_role.backend_task
  values          = { arn = "arn:aws:iam::123456789012:role/carematch-production-backend-task" }
  override_during = plan
}

override_resource {
  target          = aws_iam_role.worker_task
  values          = { arn = "arn:aws:iam::123456789012:role/carematch-production-worker-task" }
  override_during = plan
}

override_resource {
  target          = aws_iam_role.ai_worker_task
  values          = { arn = "arn:aws:iam::123456789012:role/carematch-production-ai-worker-task" }
  override_during = plan
}

override_resource {
  target          = aws_ecr_repository.backend
  values          = { arn = "arn:aws:ecr:ap-southeast-2:123456789012:repository/carematch-production-backend" }
  override_during = plan
}

override_resource {
  target          = aws_ecr_repository.frontend
  values          = { arn = "arn:aws:ecr:ap-southeast-2:123456789012:repository/carematch-production-frontend" }
  override_during = plan
}

run "production_architecture_and_security_invariants" {
  command = plan

  variables {
    acm_certificate_arn               = "arn:aws:acm:ap-southeast-2:123456789012:certificate/00000000-0000-0000-0000-000000000000"
    application_url                   = "https://carematch.example.com"
    app_key_secret_arn                = "arn:aws:secretsmanager:ap-southeast-2:123456789012:secret:carematch-app-key"
    redis_auth_token                  = "synthetic-terraform-test-token"
    redis_auth_token_secret_arn       = "arn:aws:secretsmanager:ap-southeast-2:123456789012:secret:carematch-redis"
    mail_from_address                 = "noreply@example.com"
    mail_host                         = "smtp.example.com"
    mail_username                     = "carematch-production"
    mail_password_secret_arn          = "arn:aws:secretsmanager:ap-southeast-2:123456789012:secret:carematch-mail"
    github_repository                 = "example/carematch"
    github_environment                = "production"
    github_oidc_provider_arn          = "arn:aws:iam::123456789012:oidc-provider/token.actions.githubusercontent.com"
    backend_image                     = "123456789012.dkr.ecr.ap-southeast-2.amazonaws.com/carematch-backend:test"
    frontend_image                    = "123456789012.dkr.ecr.ap-southeast-2.amazonaws.com/carematch-frontend:test"
    database_deletion_protection      = true
    candidate_documents_force_destroy = false
    care_match_ai_enabled             = false
  }

  assert {
    condition = alltrue([
      for subnet in aws_subnet.private_data : subnet.map_public_ip_on_launch == false
    ])
    error_message = "Data-tier subnets must not assign public IP addresses."
  }

  assert {
    condition     = aws_db_instance.postgres.publicly_accessible == false
    error_message = "RDS must not be publicly accessible."
  }

  assert {
    condition = (
      aws_db_instance.postgres.storage_encrypted
      && aws_db_instance.postgres.backup_retention_period >= 7
      && aws_db_instance.postgres.deletion_protection
      && aws_db_instance.postgres.multi_az == false
    )
    error_message = "RDS encryption, retention, deletion protection and the explicit Single-AZ portfolio trade-off must remain visible."
  }

  assert {
    condition = (
      aws_elasticache_replication_group.redis.at_rest_encryption_enabled
      && aws_elasticache_replication_group.redis.transit_encryption_enabled
      && aws_elasticache_replication_group.redis.auth_token != ""
      && aws_elasticache_replication_group.redis.subnet_group_name == aws_elasticache_subnet_group.main.name
    )
    error_message = "Redis must be authenticated, encrypted and placed in the private data subnet group."
  }

  assert {
    condition = (
      aws_s3_bucket_public_access_block.candidate_documents.block_public_acls
      && aws_s3_bucket_public_access_block.candidate_documents.block_public_policy
      && aws_s3_bucket_public_access_block.candidate_documents.ignore_public_acls
      && aws_s3_bucket_public_access_block.candidate_documents.restrict_public_buckets
      && aws_s3_bucket_versioning.candidate_documents.versioning_configuration[0].status == "Enabled"
      && alltrue([
        for rule in aws_s3_bucket_server_side_encryption_configuration.candidate_documents.rule : alltrue([
          for setting in rule.apply_server_side_encryption_by_default : setting.sse_algorithm == "AES256"
        ])
      ])
      && aws_s3_bucket.candidate_documents.force_destroy == false
    )
    error_message = "Candidate documents must remain private, encrypted, versioned and protected from force deletion."
  }

  assert {
    condition = (
      aws_sqs_queue.expiry_digest.sqs_managed_sse_enabled
      && aws_sqs_queue.expiry_digest.visibility_timeout_seconds > 60
      && jsondecode(aws_sqs_queue.expiry_digest.redrive_policy).maxReceiveCount == 3
      && jsondecode(aws_sqs_queue.expiry_digest.redrive_policy).deadLetterTargetArn == aws_sqs_queue.expiry_digest_dlq.arn
      && aws_sqs_queue.ai.sqs_managed_sse_enabled
      && aws_sqs_queue.ai.visibility_timeout_seconds > 120
      && jsondecode(aws_sqs_queue.ai.redrive_policy).maxReceiveCount == 3
      && jsondecode(aws_sqs_queue.ai.redrive_policy).deadLetterTargetArn == aws_sqs_queue.ai_dlq.arn
    )
    error_message = "Both SQS workloads require encryption, a DLQ, three receives and visibility beyond the worker timeout."
  }

  assert {
    condition = (
      length(aws_ecs_service.backend.load_balancer) == 1
      && length(aws_ecs_service.frontend.load_balancer) == 1
      && length(aws_ecs_service.worker.load_balancer) == 0
      && length(aws_ecs_service.ai_worker.load_balancer) == 0
      && aws_ecs_service.ai_worker.desired_count == 0
    )
    error_message = "Only HTTP services may register with the ALB, and the AI worker must remain off while AI is disabled."
  }

  assert {
    condition = (
      jsondecode(aws_ecs_task_definition.worker.container_definitions)[0].stopTimeout > 60
      && jsondecode(aws_ecs_task_definition.ai_worker.container_definitions)[0].stopTimeout > 120
    )
    error_message = "Worker stop timeouts must exceed their job timeouts."
  }

  assert {
    condition = (
      jsondecode(aws_iam_role_policy.backend_task.policy).Statement[0].Action == ["s3:GetObject", "s3:PutObject", "s3:DeleteObject"]
      && jsondecode(aws_iam_role_policy.backend_task.policy).Statement[0].Resource == "${aws_s3_bucket.candidate_documents.arn}/candidate-documents/*"
      && jsondecode(aws_iam_role_policy.worker_task.policy).Statement[0].Resource == aws_sqs_queue.expiry_digest.arn
      && jsondecode(aws_iam_role_policy.ai_worker_task.policy).Statement[1].Action == ["s3:GetObject"]
      && jsondecode(aws_iam_role_policy.ai_worker_task.policy).Statement[1].Resource == "${aws_s3_bucket.candidate_documents.arn}/candidate-documents/*"
    )
    error_message = "Task IAM must retain workload-specific SQS and least-privilege Candidate-document access."
  }

  assert {
    condition = (
      !contains(jsondecode(aws_iam_role_policy.ai_worker_task.policy).Statement[1].Action, "s3:PutObject")
      && !contains(jsondecode(aws_iam_role_policy.ai_worker_task.policy).Statement[1].Action, "s3:DeleteObject")
      && jsondecode(aws_iam_role_policy.github_deploy.policy).Statement[3].Condition.StringEquals["iam:PassedToService"] == "ecs-tasks.amazonaws.com"
      && length(jsondecode(aws_iam_role_policy.github_deploy.policy).Statement[3].Resource) == 4
    )
    error_message = "AI storage access must remain read-only and GitHub iam:PassRole must remain constrained to ECS roles."
  }

  assert {
    condition = (
      jsondecode(aws_iam_role.github_deploy.assume_role_policy).Statement[0].Condition.StringEquals["token.actions.githubusercontent.com:aud"] == "sts.amazonaws.com"
      && jsondecode(aws_iam_role.github_deploy.assume_role_policy).Statement[0].Condition.StringLike["token.actions.githubusercontent.com:sub"] == "repo:example/carematch:environment:production"
    )
    error_message = "GitHub OIDC trust must remain repository- and environment-restricted."
  }

  assert {
    condition = (
      alltrue([for secret in local.backend_secrets : contains(["APP_KEY", "DB_PASSWORD", "REDIS_PASSWORD", "MAIL_PASSWORD"], secret.name)])
      && one([for item in local.common_backend_environment : item.value if item.name == "CARE_MATCH_PUBLIC_DEMO"]) == "true"
      && !contains([for item in local.common_backend_environment : item.name], "APP_KEY")
      && !contains([for item in local.common_backend_environment : item.name], "DB_PASSWORD")
      && !contains([for item in local.common_backend_environment : item.name], "REDIS_PASSWORD")
      && !contains([for item in local.common_backend_environment : item.name], "MAIL_PASSWORD")
    )
    error_message = "Runtime credentials must be ECS secret references rather than plaintext task environment values."
  }

  assert {
    condition = (
      aws_cloudwatch_log_group.backend.retention_in_days == 30
      && aws_cloudwatch_log_group.frontend.retention_in_days == 30
      && aws_cloudwatch_log_group.worker.retention_in_days == 30
      && aws_cloudwatch_log_group.ai_worker.retention_in_days == 30
      && aws_cloudwatch_metric_alarm.dlq.threshold == 0
      && aws_cloudwatch_metric_alarm.ai_dlq.threshold == 0
      && aws_cloudwatch_metric_alarm.rds_storage.threshold > 0
      && aws_cloudwatch_metric_alarm.redis_evictions.threshold == 0
    )
    error_message = "Logs need bounded retention and queue, database and Redis alarm coverage must remain represented."
  }
}
