class RequestContext {
  const RequestContext({
    required this.requestId,
    this.idempotencyKey,
  });

  final String requestId;
  final String? idempotencyKey;
}
