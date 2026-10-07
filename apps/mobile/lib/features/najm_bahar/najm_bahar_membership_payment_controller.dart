import 'dart:math';

import 'package:flutter/foundation.dart';

import '../../core/api/api_error.dart';
import 'najm_bahar_membership_payment_dto.dart';
import 'najm_bahar_policy_dto.dart';
import 'najm_bahar_repository.dart';

enum MembershipPaymentState {
  loading,
  ready,
  confirming,
  submitting,
  confirmedPaid,
  definiteRejected,
  outcomeUnknown,
}

class MembershipPaymentController extends ChangeNotifier {
  MembershipPaymentController(
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

  MembershipPaymentState state = MembershipPaymentState.loading;
  NajmBaharMembershipFee? terms;
  MembershipPaymentReceipt? receipt;
  bool obligationPaid = false;
  ApiFailure? failure;

  MembershipSource? _selectedSource;
  String? _selectedBucket;
  MembershipPaymentIntent? _intent;
  Duration? _intentStartedAt;
  Future<void>? _pending;
  int _generation = 0;
  bool _disposed = false;
  bool _sessionInvalidated = false;

  MembershipSource? get selectedSource => _selectedSource;
  String? get selectedBucket => _selectedBucket;
  bool get hasFrozenIntent => _intent != null;

  Future<void> prepare() async {
    if (_disposed || _sessionInvalidated) return;
    if (state == MembershipPaymentState.outcomeUnknown ||
        state == MembershipPaymentState.submitting) {
      await reconcile();
      return;
    }

    final generation = ++_generation;
    state = MembershipPaymentState.loading;
    failure = null;
    notifyListeners();

    try {
      final next = await _repository.membershipFee();
      if (!_canPublish(generation)) return;
      terms = next;
      receipt = null;
      obligationPaid = next.hasPaid;
      _selectedSource = null;
      _selectedBucket = null;
      _intent = null;
      _intentStartedAt = null;
      state = next.hasPaid
          ? MembershipPaymentState.confirmedPaid
          : MembershipPaymentState.ready;
      notifyListeners();
    } on ApiFailure catch (error) {
      if (!_canPublish(generation)) return;
      _handleFailureWithoutMutation(error);
    }
  }

  void selectSource(MembershipSource source, String bucket) {
    if (_disposed ||
        state != MembershipPaymentState.ready ||
        terms == null ||
        _intent != null) {
      return;
    }
    try {
      MembershipPaymentIntent(
        terms: terms!,
        source: source,
        bucket: bucket,
        key: 'selection-validation',
      );
    } on ArgumentError {
      return;
    }
    _selectedSource = source;
    _selectedBucket = bucket;
    failure = null;
    notifyListeners();
  }

  void beginConfirmation() {
    if (_disposed ||
        state != MembershipPaymentState.ready ||
        _selectedSource == null ||
        _selectedBucket == null ||
        terms == null ||
        _intent != null) {
      return;
    }
    state = MembershipPaymentState.confirming;
    failure = null;
    notifyListeners();
  }

  Future<void> confirm() {
    if (_disposed || _sessionInvalidated) return Future<void>.value();
    if (_pending != null) return _pending!;
    if (state != MembershipPaymentState.confirming ||
        terms == null ||
        _selectedSource == null ||
        _selectedBucket == null) {
      return Future<void>.value();
    }

    _intent ??= MembershipPaymentIntent(
      terms: terms!,
      source: _selectedSource!,
      bucket: _selectedBucket!,
      key: _keyFactory(),
    );
    _intentStartedAt ??= _elapsedSinceStart();
    _pending = _submitFrozenIntent();
    return _pending!;
  }

  Future<void> retrySameIntent() {
    if (_disposed || _sessionInvalidated) return Future<void>.value();
    if (_pending != null) return _pending!;
    if (state != MembershipPaymentState.outcomeUnknown || _intent == null) {
      return Future<void>.value();
    }
    if (_intentExpired) {
      failure = const ApiFailure(
        code: 'payment_intent_expired',
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
    if (_disposed || _sessionInvalidated || _intent == null) return;
    final frozen = _intent!;
    final generation = _generation;
    try {
      final current = await _repository.membershipFee();
      if (!_canPublish(generation)) return;
      if (current.paymentYear != frozen.terms.paymentYear) {
        if (state != MembershipPaymentState.submitting) {
          state = MembershipPaymentState.outcomeUnknown;
          obligationPaid = false;
          notifyListeners();
        }
        return;
      }
      if (current.hasPaid) {
        terms = current;
        obligationPaid = true;
        failure = null;
        state = MembershipPaymentState.confirmedPaid;
        notifyListeners();
        await _refreshAfterKnownSuccess();
      }
    } on ApiFailure catch (error) {
      if (!_canPublish(generation)) return;
      if (_isSessionFailure(error)) {
        invalidateSession();
      }
    }
  }

  void invalidateSession() {
    if (_disposed || _sessionInvalidated) return;
    _sessionInvalidated = true;
    _generation++;
    terms = null;
    receipt = null;
    obligationPaid = false;
    failure = const ApiFailure(
      code: 'session_changed',
      message: '',
      retryable: false,
      httpStatus: 401,
    );
    _selectedSource = null;
    _selectedBucket = null;
    _intent = null;
    _intentStartedAt = null;
    state = MembershipPaymentState.loading;
    _onSessionInvalidated?.call();
    notifyListeners();
  }

  Future<void> _submitFrozenIntent() async {
    final frozen = _intent!;
    final generation = _generation;
    state = MembershipPaymentState.submitting;
    failure = null;
    notifyListeners();

    try {
      final result = await _repository.payMembership(frozen);
      if (!_canPublish(generation)) return;
      receipt = result;
      obligationPaid = true;
      failure = null;
      state = MembershipPaymentState.confirmedPaid;
      notifyListeners();
      await _refreshAfterKnownSuccess();
    } on ApiFailure catch (error) {
      if (!_canPublish(generation)) return;
      if (state == MembershipPaymentState.confirmedPaid) return;
      failure = error;

      if (_isSessionFailure(error)) {
        invalidateSession();
      } else if (error.code == 'bootstrap_unavailable') {
        _intent = null;
        _intentStartedAt = null;
        state = MembershipPaymentState.ready;
        notifyListeners();
      } else if (error.code == 'already_paid') {
        state = MembershipPaymentState.outcomeUnknown;
        notifyListeners();
        await reconcile();
      } else if (_isUnknownOutcome(error)) {
        state = MembershipPaymentState.outcomeUnknown;
        notifyListeners();
      } else {
        _intent = null;
        _intentStartedAt = null;
        state = MembershipPaymentState.definiteRejected;
        notifyListeners();
      }
    } finally {
      _pending = null;
    }
  }

  void _handleFailureWithoutMutation(ApiFailure error) {
    failure = error;
    if (_isSessionFailure(error)) {
      invalidateSession();
      return;
    }
    state = MembershipPaymentState.ready;
    notifyListeners();
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
      // Known payment success is authoritative; refresh is best-effort only.
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
    return 'membership-$now-$entropy';
  }
}
