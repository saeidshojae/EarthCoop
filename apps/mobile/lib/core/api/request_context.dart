class RequestContext {
  const RequestContext({
    this.requestId,
    this.idempotencyKey,
    this.deviceId,
    this.allowAutomaticRetry = true,
  });

  final String? requestId;
  final String? idempotencyKey;
  final String? deviceId;
  final bool allowAutomaticRetry;
}
