resource "aws_sqs_queue" "expiry_digest_dlq" {
  name                      = "${local.name}-compliance-dlq"
  message_retention_seconds = 1209600
  sqs_managed_sse_enabled   = true
}

resource "aws_sqs_queue" "expiry_digest" {
  name                       = "${local.name}-compliance"
  visibility_timeout_seconds = 90
  receive_wait_time_seconds  = 20
  message_retention_seconds  = 345600
  sqs_managed_sse_enabled    = true
  redrive_policy = jsonencode({
    deadLetterTargetArn = aws_sqs_queue.expiry_digest_dlq.arn
    maxReceiveCount     = 3
  })
}

resource "aws_sqs_queue_redrive_allow_policy" "expiry_digest" {
  queue_url = aws_sqs_queue.expiry_digest_dlq.id
  redrive_allow_policy = jsonencode({
    redrivePermission = "byQueue"
    sourceQueueArns   = [aws_sqs_queue.expiry_digest.arn]
  })
}

resource "aws_sqs_queue" "ai_dlq" {
  name                      = "${local.name}-ai-dlq"
  message_retention_seconds = 1209600
  sqs_managed_sse_enabled   = true
}

resource "aws_sqs_queue" "ai" {
  name                       = "${local.name}-ai"
  visibility_timeout_seconds = 180
  receive_wait_time_seconds  = 20
  message_retention_seconds  = 345600
  sqs_managed_sse_enabled    = true
  redrive_policy = jsonencode({
    deadLetterTargetArn = aws_sqs_queue.ai_dlq.arn
    maxReceiveCount     = 3
  })
}

resource "aws_sqs_queue_redrive_allow_policy" "ai" {
  queue_url = aws_sqs_queue.ai_dlq.id
  redrive_allow_policy = jsonencode({
    redrivePermission = "byQueue"
    sourceQueueArns   = [aws_sqs_queue.ai.arn]
  })
}
