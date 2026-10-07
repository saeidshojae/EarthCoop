import 'dart:math';

import 'package:flutter/foundation.dart';

import '../../core/api/api_error.dart';
import 'najm_bahar_activation_dto.dart';
import 'najm_bahar_repository.dart';

enum NajmBaharActivationState {
  loading,
  ready,
  reviewing,
  submitting,
  confirmed,
  definiteRejected,
  outcomeUnknown,
}

class NajmBaharActivationController extends ChangeNotifier {
  NajmBaharActivationController(
    this._repository, {
    Listenable? sessionChanges,
    VoidCallback? onSessionInvalidated,
    String Function()? keyFactory,
    Duration Function()? elapsedSinceStart,
    Future<void> Function()? refreshFinancialViews,
  })  : _sessionChanges = sessionChanges,
        _onSessionInvalidated = onSessionInvalidated,
        _keyFactory = keyFactory ?? _secureKey,
        _refreshFinancialViews = refreshFinancialViews {
    if (elapsedSinceStart == null) {
      _stopwatch.start();
      _elapsedSinceStart = () => _stopwatch.elapsed;
    } else {
      _elapsedSinceStart = elapsedSinceStart;
    }
    _sessionChanges?.addListener(_handleSessionChange);
  }

  final NajmBaharRepository _repository;
  final Listenable? _sessionChanges;
  final VoidCallback? _onSessionInvalidated;
  final String Function() _keyFactory;
  final Future<void> Function()? _refreshFinancialViews;
  final Stopwatch _stopwatch = Stopwatch();
  late final Duration Function() _elapsedSinceStart;

  NajmBaharActivationState state = NajmBaharActivationState.loading;
  NajmBaharActivationEligibilityV1? eligibility;
  NajmBaharActivationReceipt? receipt;
  ApiFailure? failure;

  int? _reviewPoints;
  NajmBaharActivationIntent? _intent;
  Duration? _intentStartedAt;
  Future<void>? _pending;
  int _generation = 0;
  bool _disposed = false;
  bool _sessionInvalidated = false;

  int? get reviewPoints => _reviewPoints;
  int? get reviewActivatedGol => _reviewPoints == null || eligibility == null
      ? null
      : _reviewPoints! ~/ eligibility!.pointsPerGol;
  bool get hasFrozenIntent => _intent != null;

  Future<void> prepare() async {
    if (_disposed || _sessionInvalidated) return;
    if (state == NajmBaharActivationState.outcomeUnknown ||
        state == NajmBaharActivationState.submitting) {
      await reconcile();
      return;
    }

    final generation = ++_generation;
    state = NajmBaharActivationState.loading;
    failure = null;
    notifyListeners();

    try {
      final next = await _repository.activationEligibilityV1();
      if (!_canPublish(generation)) return;
      eligibility = next;
      receipt = null;
      _reviewPoints = null;
      _intent = null;
      _intentStartedAt = null;
      state = NajmBaharActivationState.ready;
      notifyListeners();
    } on ApiFailure catch (error) {
      if (!_canPublish(generation)) return;
      failure = error;
      if (_isSessionFailure(error)) {
        invalidateSession();
        return;
      }
      state = NajmBaharActivationState.ready;
      notifyListeners();
    }
  }

  void beginReview(int points) {
    if (_disposed ||
        _sessionInvalidated ||
        state != NajmBaharActivationState.ready ||
        eligibility == null ||
        _intent != null) {
      return;
    }

    try {
      final validation = NajmBaharActivationIntent(
        eligibility: eligibility!,
        points: points,
        key: 'selection-validation',
      );
      _reviewPoints = validation.points;
    } on ArgumentError {
      return;
    }

    failure = null;
    state = NajmBaharActivationState.reviewing;
    notifyListeners();
  }

  void cancelReview() {
    if (_disposed ||
        state != NajmBaharActivationState.reviewing ||
        _intent != null) {
      return;
    }
    _reviewPoints = null;
    state = NajmBaharActivationState.ready;
    notifyListeners();
  }

  Future<void> confirm() {
    if (_disposed || _sessionInvalidated) return Future<void>.value();
    if (_pending != null) return _pending!;
    if (state != NajmBaharActivationState.reviewing ||
        eligibility == null ||
        _reviewPoints == null) {
      return Future<void>.value();
    }

    _intent ??= NajmBaharActivationIntent(
      eligibility: eligibility!,
      points: _reviewPoints!,
      key: _keyFactory(),
    );
    _intentStartedAt ??= _elapsedSinceStart();
    _pending = _submitFrozenIntent();
    return _pending!;
  }

  Future<void> retrySameIntent() {
    if (_disposed || _sessionInvalidated) return Future<void>.value();
    if (_pending != null) return _pending!;
    if (state != NajmBaharActivationState.outcomeUnknown || _intent == null) {
      return Future<void>.value();
    }

    if (_intentExpired) {
      failure = const ApiFailure(
        code: 'activation_intent_expired',
        message: '',
        retryable: false,
      );
      notifyListeners();
      return Future<void>.value();
    }

    _pending = _submitFrozenIntent();
    return _pending!;
  }

  Future<void> reconcile() async {
    if (_disposed ||
        _sessionInvalidated ||
        _intent == null ||
        state == NajmBaharActivationState.confirmed) {
      return;
    }

    final frozen = _intent!;
    final generation = _generation;
    try {
      final result = await _repository.reconcileActivation(frozen);
      if (!_canPublish(generation)) return;
      receipt = result;
      failure = null;
      state = NajmBaharActivationState.confirmed;
      notifyListeners();
      await _refreshAfterKnownSuccess();
    } on ApiFailure catch (error) {
      if (!_canPublish(generation)) return;
      if (_isSessionFailure(error)) {
        invalidateSession();
        return;
      }
      if (error.code == 'not_found') {
        failure = null;
        state = NajmBaharActivationState.outcomeUnknown;
        notifyListeners();
        return;
      }
      failure = error;
      state = NajmBaharActivationState.outcomeUnknown;
      notifyListeners();
    }
  }

  void invalidateSession() {
    if (_disposed || _sessionInvalidated) return;
    _sessionInvalidated = true;
    _generation++;
    eligibility = null;
    receipt = null;
    failure = const ApiFailure(
      code: 'session_changed',
      message: '',
      retryable: false,
      httpStatus: 401,
    );
    _reviewPoints = null;
    _intent = null;
    _intentStartedAt = null;
    state = NajmBaharActivationState.loading;
    _onSessionInvalidated?.call();
    notifyListeners();
  }

  Future<void> _submitFrozenIntent() async {
    final frozen = _intent!;
    final generation = _generation;
    state = NajmBaharActivationState.submitting;
    failure = null;
    notifyListeners();

    try {
      final result = await _repository.activateParticipation(frozen);
      if (!_canPublish(generation)) return;
      receipt = result;
      failure = null;
      state = NajmBaharActivationState.confirmed;
      notifyListeners();
      await _refreshAfterKnownSuccess();
    } on ApiFailure catch (error) {
      if (!_canPublish(generation)) return;
      if (state == NajmBaharActivationState.confirmed) return;
      failure = error;

      if (_isSessionFailure(error)) {
        invalidateSession();
      } else if (error.code == 'bootstrap_unavailable') {
        _intent = null;
        _intentStartedAt = null;
        state = NajmBaharActivationState.ready;
        notifyListeners();
      } else if (_isUnknownOutcome(error)) {
        state = NajmBaharActivationState.outcomeUnknown;
        notifyListeners();
      } else {
        _intent = null;
        _intentStartedAt = null;
        state = NajmBaharActivationState.definiteRejected;
        notifyListeners();
      }
    } finally {
      _pending = null;
    }
  }

  bool _isUnknownOutcome(ApiFailure error) =>
      error.code == 'network_error' ||
      error.code == 'malformed_response' ||
      error.code == 'request_in_progress' ||
      error.retryable;

  bool _isSessionFailure(ApiFailure error) =>
      error.code == 'session_changed' ||
      error.code == 'unauthenticated' ||
      error.httpStatus == 401;

  bool get _intentExpired {
    final started = _intentStartedAt;
    if (started == null) return false;
    return _elapsedSinceStart() - started >= const Duration(hours: 23);
  }

  bool _canPublish(int generation) =>
      !_disposed &&
      !_sessionInvalidated &&
      generation == _generation &&
      _repository.isCurrentSession;

  Future<void> _refreshAfterKnownSuccess() async {
    try {
      await _refreshFinancialViews?.call();
    } catch (_) {
      // Known activation success is authoritative; refresh is best-effort.
    }
  }

  void _handleSessionChange() {
    if (_disposed) return;
    invalidateSession();
  }

  @override
  void dispose() {
    if (_disposed) return;
    _disposed = true;
    _generation++;
    _sessionChanges?.removeListener(_handleSessionChange);
    _stopwatch.stop();
    super.dispose();
  }

  static String _secureKey() {
    final random = Random.secure();
    final now = DateTime.now().microsecondsSinceEpoch.toRadixString(36);
    final entropy = List<int>.generate(4, (_) => random.nextInt(1 << 32))
        .map((value) => value.toRadixString(36).padLeft(7, '0'))
        .join();
    return 'activation-$now-$entropy';
  }
}
