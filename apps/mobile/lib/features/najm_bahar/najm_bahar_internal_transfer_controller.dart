import 'dart:math';

import 'package:flutter/foundation.dart';

import '../../core/api/api_error.dart';
import 'najm_bahar_internal_transfer_dto.dart';
import 'najm_bahar_repository.dart';

enum NajmBaharInternalTransferState {
  loading,
  ready,
  reviewing,
  submitting,
  confirmed,
  definiteRejected,
  outcomeUnknown,
}

class NajmBaharInternalTransferController extends ChangeNotifier {
  NajmBaharInternalTransferController(
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

  NajmBaharInternalTransferState state = NajmBaharInternalTransferState.loading;
  NajmBaharSubAccountSnapshot? snapshot;
  NajmBaharInternalTransferReceipt? receipt;
  ApiFailure? failure;

  NajmBaharInternalTransferIntent? _reviewIntent;
  NajmBaharInternalTransferIntent? _intent;
  Duration? _intentStartedAt;
  Future<void>? _pending;
  int _generation = 0;
  bool _disposed = false;
  bool _sessionInvalidated = false;

  NajmBaharInternalTransferIntent? get reviewIntent => _reviewIntent;
  bool get hasFrozenIntent => _intent != null;

  Future<void> prepare() async {
    if (_disposed || _sessionInvalidated) return;
    if (state == NajmBaharInternalTransferState.outcomeUnknown ||
        state == NajmBaharInternalTransferState.submitting) {
      await reconcile();
      return;
    }

    final generation = ++_generation;
    state = NajmBaharInternalTransferState.loading;
    failure = null;
    notifyListeners();

    try {
      final next = await _repository.subAccounts();
      if (!_canPublish(generation)) return;
      snapshot = next;
      receipt = null;
      _reviewIntent = null;
      _intent = null;
      _intentStartedAt = null;
      state = NajmBaharInternalTransferState.ready;
      notifyListeners();
    } on ApiFailure catch (error) {
      if (!_canPublish(generation)) return;
      failure = error;
      if (_isSessionFailure(error)) {
        invalidateSession();
        return;
      }
      state = NajmBaharInternalTransferState.ready;
      notifyListeners();
    }
  }

  Future<void> createSubAccount(String? name) async {
    if (!_canStartNonFinancialMutation) return;
    final generation = _generation;
    try {
      await _repository.createSubAccount(name, _keyFactory());
      if (!_canPublish(generation)) return;
      await prepare();
    } on ApiFailure catch (error) {
      if (!_canPublish(generation)) return;
      failure = error;
      if (_isSessionFailure(error)) {
        invalidateSession();
        return;
      }
      notifyListeners();
    }
  }

  Future<void> renameSubAccount(int subAccountId, String name) async {
    if (!_canStartNonFinancialMutation) return;
    final generation = _generation;
    try {
      await _repository.renameSubAccount(
        subAccountId,
        name,
        _keyFactory(),
      );
      if (!_canPublish(generation)) return;
      await prepare();
    } on ApiFailure catch (error) {
      if (!_canPublish(generation)) return;
      failure = error;
      if (_isSessionFailure(error)) {
        invalidateSession();
        return;
      }
      notifyListeners();
    }
  }

  void beginReview({
    required NajmBaharInternalAccountRef source,
    required NajmBaharInternalAccountRef destination,
    required String balanceBucket,
    required int amountGol,
    String? description,
  }) {
    if (_disposed ||
        _sessionInvalidated ||
        state != NajmBaharInternalTransferState.ready ||
        snapshot == null ||
        _intent != null ||
        !_isCurrentAccount(source) ||
        !_isCurrentAccount(destination)) {
      return;
    }

    final direction = _direction(source, destination);
    if (direction == null) return;

    try {
      _reviewIntent = NajmBaharInternalTransferIntent(
        contractVersion: snapshot!.contractVersion,
        direction: direction,
        source: source,
        destination: destination,
        balanceBucket: balanceBucket,
        amountGol: amountGol,
        description: description,
        key: 'selection-validation',
      );
    } on ArgumentError {
      return;
    }

    failure = null;
    state = NajmBaharInternalTransferState.reviewing;
    notifyListeners();
  }

  void cancelReview() {
    if (_disposed ||
        state != NajmBaharInternalTransferState.reviewing ||
        _intent != null) {
      return;
    }
    _reviewIntent = null;
    state = NajmBaharInternalTransferState.ready;
    notifyListeners();
  }

  Future<void> confirm() {
    if (_disposed || _sessionInvalidated) return Future<void>.value();
    if (_pending != null) return _pending!;
    final review = _reviewIntent;
    if (state != NajmBaharInternalTransferState.reviewing || review == null) {
      return Future<void>.value();
    }

    _intent ??= NajmBaharInternalTransferIntent(
      contractVersion: review.contractVersion,
      direction: review.direction,
      source: review.source,
      destination: review.destination,
      balanceBucket: review.balanceBucket,
      amountGol: review.amountGol,
      description: review.description,
      key: _keyFactory(),
    );
    _intentStartedAt ??= _elapsedSinceStart();
    _pending = _submitFrozenIntent();
    return _pending!;
  }

  Future<void> retrySameIntent() {
    if (_disposed || _sessionInvalidated) return Future<void>.value();
    if (_pending != null) return _pending!;
    if (state != NajmBaharInternalTransferState.outcomeUnknown ||
        _intent == null) {
      return Future<void>.value();
    }

    if (_intentExpired) {
      failure = const ApiFailure(
        code: 'internal_transfer_intent_expired',
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
        state == NajmBaharInternalTransferState.confirmed) {
      return;
    }

    final frozen = _intent!;
    final generation = _generation;
    try {
      final result = await _repository.reconcileInternalTransfer(frozen);
      if (!_canPublish(generation)) return;
      receipt = result;
      failure = null;
      state = NajmBaharInternalTransferState.confirmed;
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
        state = NajmBaharInternalTransferState.outcomeUnknown;
        notifyListeners();
        return;
      }
      failure = error;
      state = NajmBaharInternalTransferState.outcomeUnknown;
      notifyListeners();
    }
  }

  void invalidateSession() {
    if (_disposed || _sessionInvalidated) return;
    _sessionInvalidated = true;
    _generation++;
    snapshot = null;
    receipt = null;
    failure = const ApiFailure(
      code: 'session_changed',
      message: '',
      retryable: false,
      httpStatus: 401,
    );
    _reviewIntent = null;
    _intent = null;
    _intentStartedAt = null;
    state = NajmBaharInternalTransferState.loading;
    _onSessionInvalidated?.call();
    notifyListeners();
  }

  Future<void> _submitFrozenIntent() async {
    final frozen = _intent!;
    final generation = _generation;
    state = NajmBaharInternalTransferState.submitting;
    failure = null;
    notifyListeners();

    try {
      final result = await _repository.sendInternalTransfer(frozen);
      if (!_canPublish(generation)) return;
      receipt = result;
      failure = null;
      state = NajmBaharInternalTransferState.confirmed;
      notifyListeners();
      await _refreshAfterKnownSuccess();
    } on ApiFailure catch (error) {
      if (!_canPublish(generation)) return;
      if (state == NajmBaharInternalTransferState.confirmed) return;
      failure = error;

      if (_isSessionFailure(error)) {
        invalidateSession();
      } else if (error.code == 'bootstrap_unavailable') {
        _intent = null;
        _intentStartedAt = null;
        state = NajmBaharInternalTransferState.ready;
        notifyListeners();
      } else if (_isUnknownOutcome(error)) {
        state = NajmBaharInternalTransferState.outcomeUnknown;
        notifyListeners();
      } else {
        _intent = null;
        _intentStartedAt = null;
        state = NajmBaharInternalTransferState.definiteRejected;
        notifyListeners();
      }
    } finally {
      _pending = null;
    }
  }

  bool get _canStartNonFinancialMutation =>
      !_disposed &&
      !_sessionInvalidated &&
      state == NajmBaharInternalTransferState.ready &&
      _intent == null;

  bool _isCurrentAccount(NajmBaharInternalAccountRef candidate) {
    final current = snapshot;
    if (current == null) return false;
    if (candidate.subAccountId == null) {
      return current.main.accountId == candidate.accountId &&
          current.main.accountNumber == candidate.accountNumber;
    }
    return current.subaccounts.any((account) =>
        account.subAccountId == candidate.subAccountId &&
        account.accountId == candidate.accountId &&
        account.accountNumber == candidate.accountNumber);
  }

  String? _direction(
    NajmBaharInternalAccountRef source,
    NajmBaharInternalAccountRef destination,
  ) {
    if (source.accountNumber == destination.accountNumber) return null;
    if (source.subAccountId == null && destination.subAccountId != null) {
      return 'main_to_sub';
    }
    if (source.subAccountId != null && destination.subAccountId == null) {
      return 'sub_to_main';
    }
    if (source.subAccountId != null && destination.subAccountId != null) {
      return 'sub_to_sub';
    }
    return null;
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
      // A known internal-transfer success remains authoritative.
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
    return 'internal-$now-$entropy';
  }
}
