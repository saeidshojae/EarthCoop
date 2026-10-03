class RequestContext {
  const RequestContext({
    this.requestId,
    this.idempotencyKey,
    this.deviceId,
  });

  final String? requestId;
  final String? idempotencyKey;
  final String? deviceId;
}
