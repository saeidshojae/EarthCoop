enum BootstrapDecision {
  compatible,
  recommendedUpdate,
  requiredUpdate,
  degradedOffline,
  unavailable,
}

class BootstrapState {
  const BootstrapState._({
    required this.decision,
    required this.isFresh,
    required this.allowsProductShell,
    required this.allowsProtectedNetwork,
    required this.canReplayQueuedMutations,
    required this.requiresFreshBootstrap,
  });

  const BootstrapState.compatible()
      : this._(
          decision: BootstrapDecision.compatible,
          isFresh: true,
          allowsProductShell: true,
          allowsProtectedNetwork: true,
          canReplayQueuedMutations: true,
          requiresFreshBootstrap: false,
        );

  const BootstrapState.recommendedUpdate()
      : this._(
          decision: BootstrapDecision.recommendedUpdate,
          isFresh: true,
          allowsProductShell: true,
          allowsProtectedNetwork: true,
          canReplayQueuedMutations: true,
          requiresFreshBootstrap: false,
        );

  const BootstrapState.requiredUpdate()
      : this._(
          decision: BootstrapDecision.requiredUpdate,
          isFresh: true,
          allowsProductShell: false,
          allowsProtectedNetwork: false,
          canReplayQueuedMutations: false,
          requiresFreshBootstrap: false,
        );

  const BootstrapState.degradedOffline()
      : this._(
          decision: BootstrapDecision.degradedOffline,
          isFresh: false,
          allowsProductShell: true,
          allowsProtectedNetwork: false,
          canReplayQueuedMutations: false,
          requiresFreshBootstrap: true,
        );

  const BootstrapState.unavailable()
      : this._(
          decision: BootstrapDecision.unavailable,
          isFresh: false,
          allowsProductShell: false,
          allowsProtectedNetwork: false,
          canReplayQueuedMutations: false,
          requiresFreshBootstrap: true,
        );

  final BootstrapDecision decision;
  final bool isFresh;
  final bool allowsProductShell;
  final bool allowsProtectedNetwork;
  final bool canReplayQueuedMutations;
  final bool requiresFreshBootstrap;
}
