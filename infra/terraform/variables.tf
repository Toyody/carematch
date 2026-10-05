variable "aws_region" {
  type        = string
  description = "AWS region for the CareMatch environment."
  default     = "ap-southeast-2"
}

variable "environment" {
  type        = string
  description = "Short environment name used in resource names."
  default     = "production"
}

variable "name_prefix" {
  type        = string
  description = "Resource-name prefix."
  default     = "carematch"
}

variable "vpc_cidr" {
  type        = string
  description = "VPC CIDR."
  default     = "10.42.0.0/16"
}

variable "availability_zones" {
  type        = list(string)
  description = "Two availability zones used for subnet placement; workloads remain single-task/single-node."
  default     = ["ap-southeast-2a", "ap-southeast-2b"]

  validation {
    condition     = length(var.availability_zones) == 2
    error_message = "Exactly two availability zones are required."
  }
}

variable "acm_certificate_arn" {
  type        = string
  description = "Existing ACM certificate ARN. DNS ownership remains external to this Terraform root."
}

variable "application_url" {
  type        = string
  description = "Canonical HTTPS same-origin application URL."
}

variable "app_key_secret_arn" {
  type        = string
  description = "Secrets Manager ARN containing the Laravel APP_KEY value."
  sensitive   = true
}

variable "redis_auth_token" {
  type        = string
  description = "ElastiCache auth token supplied securely by the Phase 6B operator; it is sensitive Terraform state."
  sensitive   = true
}

variable "redis_auth_token_secret_arn" {
  type        = string
  description = "Secrets Manager ARN exposing the same Redis auth token to ECS."
  sensitive   = true
}

variable "mail_mailer" {
  type        = string
  description = "Configured Laravel mail transport name."
  default     = "smtp"
}

variable "mail_from_address" {
  type        = string
  description = "Verified production sender address."
}

variable "mail_host" {
  type        = string
  description = "Production SMTP host selected by the Phase 6B operator."
}

variable "mail_port" {
  type        = number
  description = "Production SMTP port."
  default     = 587
}

variable "mail_username" {
  type        = string
  description = "Production SMTP username."
}

variable "mail_password_secret_arn" {
  type        = string
  description = "Secrets Manager ARN containing the production SMTP password."
  sensitive   = true
}

variable "alarm_action_arn" {
  type        = string
  description = "Optional SNS topic ARN for alarm notifications."
  default     = ""
}

variable "github_repository" {
  type        = string
  description = "GitHub owner/repository permitted to assume the deployment role."
}

variable "github_environment" {
  type        = string
  description = "Protected GitHub Environment permitted to deploy."
  default     = "production"
}

variable "github_oidc_provider_arn" {
  type        = string
  description = "Existing GitHub Actions OIDC provider ARN. Terraform does not create account-global provider state."
}

variable "backend_image" {
  type        = string
  description = "Initial immutable backend ECR image URI. The deployment workflow replaces it by SHA."
}

variable "frontend_image" {
  type        = string
  description = "Initial immutable frontend ECR image URI. The deployment workflow replaces it by SHA."
}

variable "database_name" {
  type        = string
  default     = "carematch"
  description = "PostgreSQL database name."
}

variable "database_username" {
  type        = string
  default     = "carematch"
  description = "RDS master username; RDS manages the generated password in Secrets Manager."
}

variable "database_deletion_protection" {
  type        = bool
  default     = true
  description = "Protect production RDS from accidental deletion."
}

variable "candidate_documents_force_destroy" {
  type        = bool
  default     = false
  description = "Must remain false in production; explicit escape hatch for disposable validation environments."
}

variable "care_match_ai_enabled" {
  type        = bool
  default     = false
  description = "Explicit opt-in for external AI processing and the AI worker service."
}

variable "ai_provider" {
  type        = string
  default     = "openai"
  description = "Configured AI provider adapter name."
}

variable "ai_model" {
  type        = string
  default     = ""
  description = "Provider model selected by the operator; required before enabling AI."
}

variable "openai_api_key_secret_arn" {
  type        = string
  default     = ""
  description = "Secrets Manager ARN containing the OpenAI API key. Required only when AI is enabled."
  sensitive   = true

  validation {
    condition     = !var.care_match_ai_enabled || (var.ai_model != "" && var.openai_api_key_secret_arn != "")
    error_message = "AI model and OpenAI secret ARN are required when CareMatch AI is enabled."
  }
}

locals {
  name          = "${var.name_prefix}-${var.environment}"
  alarm_actions = var.alarm_action_arn == "" ? [] : [var.alarm_action_arn]
}
