import 'dart:math';

import 'package:flutter/foundation.dart';

import '../../core/api/api_error.dart';
import 'najm_bahar_repository.dart';
import 'najm_bahar_transfer_dto.dart';

enum NajmBaharTransferState {
  loadingCapability,
  ready,
  resolvingDestination,
  reviewing,
  submitting,
  confirmed,
  definiteRejected,
  outcomeUnknown,
}

class NajmBaharTransferController extends ChangeNotifier {
  NajmBaharTransferController(
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

  NajmBaharTransferState state = NajmBaharTransferState.loadingCapability;
  NajmBaharTransferCapability? capability;
  NajmBaharTransferDestination? destination;
  NajmBaharTransferReceipt? receipt;
  ApiFailure? failure;

  NajmBaharTransferSource? _selectedSource;
  int? _reviewAmountGol;
  String? _reviewDescription;
  NajmBaharTransferIntent? _intent;
  Duration? _intentStartedAt;
  Future<void>? _pending;
  int _generation = 0;
  bool _disposed = false;
  bool _sessionInvalidated = false;

  NajmBaharTransferSource? get selectedSource => _selectedSource;
  int? get reviewAmountGol => _reviewAmountGol;
  String? get reviewDescription => _reviewDescription;
  bool get hasFrozenIntent => _intent != null;

  Future<void> prepare() async {
    if (_disposed || _sessionInvalidated) return;
    if (state == NajmBaharTransferState.outcomeUnknown ||
        state == NajmBaharTransferState.submitting) {
      await reconcile();
      return;
    }

    final generation = ++_generation;
    state = NajmBaharTransferState.loadingCapability;
    failure = null;
    notifyListeners();

    try {
      final next = await _repository.transferCapability();
      if (!_canPublish(generation)) return;
      capability = next;
      destination = null;
      receipt = null;
      _selectedSource = null;
      _reviewAmountGol = null;
      _reviewDescription = null;
      _intent = null;
      _intentStartedAt = null;
      state = NajmBaharTransferState.ready;
      notifyListeners();
    } on ApiFailure catch (error) {
      if (!_canPublish(generation)) return;
      failure = error;
      if (_isSessionFailure(error)) {
        invalidateSession();
        return;
      }
      state = NajmBaharTransferState.ready;
      notifyListeners();
    }
  }

  void selectSource(NajmBaharTransferSource source) {
    if (_disposed ||
        state != NajmBaharTransferState.ready ||
        _intent != null ||
        capability == null ||
        !capability!.externalTransferEnabled) {
      return;
    }

    final valid = capability!.sources.any((candidate) =>
        candidate.accountId == source.accountId &&
        candidate.accountNumber == source.accountNumber &&
        candidate.subAccountId == source.subAccountId &&
        candidate.canTransferActive);
    if (!valid) return;

    _selectedSource = source;
    destination = null;
    _reviewAmountGol = null;
    _reviewDescription = null;
    failure = null;
    notifyListeners();
  }

  Future<void> resolveDestination(String accountNumber) async {
    if (_disposed ||
        _sessionInvalidated ||
        state != NajmBaharTransferState.ready ||
        _intent != null ||
        _selectedSource == null ||
        capability?.externalTransferEnabled != true) {
      return;
    }

    final generation = _generation;
    state = NajmBaharTransferState.resolvingDestination;
    destination = null;
    failure = null;
    notifyListeners();

    try {
      final resolved = await _repository.transferDestination(accountNumber);
      if (!_canPublish(generation)) return;
      destination = resolved;
      state = NajmBaharTransferState.ready;
      notifyListeners();
    } on ApiFailure catch (error) {
      if (!_canPublish(generation)) return;
      failure = error;
      if (_isSessionFailure(error)) {
        invalidateSession();
        return;
      }
      state = NajmBaharTransferState.ready;
      notifyListeners();
    }
  }

  void beginReview({required int amountGol, String? description}) {
    if (_disposed ||
        state != NajmBaharTransferState.ready ||
        _intent != null ||
        capability?.externalTransferEnabled != true ||
        _selectedSource == null ||
        destination == null) {
      return;
    }

    try {
      final validation = NajmBaharTransferIntent(
        contractVersion: capability!.contractVersion,
        source: _selectedSource!,
        destination: destination!,
        amountGol: amountGol,
        description: description,
        key: 'selection-validation',
      );
      _reviewAmountGol = validation.amountGol;
      _reviewDescription = validation.description;
    } on ArgumentError {
      return;
    }

    failure = null;
    state = NajmBaharTransferState.reviewing;
    notifyListeners();
  }

  void cancelReview() {
    if (_disposed ||
        state != NajmBaharTransferState.reviewing ||
        _intent != null) {
      return;
    }
    _reviewAmountGol = null;
    _reviewDescription = null;
    state = NajmBaharTransferState.ready;
    notifyListeners();
  }

  Future<void> confirm() {
    if (_disposed || _sessionInvalidated) return Future<void>.value();
    if (_pending != null) return _pending!;
    if (state != NajmBaharTransferState.reviewing ||
        capability == null ||
        _selectedSource == null ||
        destination == null ||
        _reviewAmountGol == null) {
      return Future<void>.value();
    }

    _intent ??= NajmBaharTransferIntent(
      contractVersion: capability!.contractVersion,
      source: _selectedSource!,
      destination: destination!,
      amountGol: _reviewAmountGol!,
      description: _reviewDescription,
      key: _keyFactory(),
    );
    _intentStartedAt ??= _elapsedSinceStart();
    _pending = _submitFrozenIntent();
    return _pending!;
  }

  Future<void> retrySameIntent() {
    if (_disposed || _sessionInvalidated) return Future<void>.value();
    if (_pending != null) return _pending!;
    if (state != NajmBaharTransferState.outcomeUnknown || _intent == null) {
      return Future<void>.value();
    }

    if (_intentExpired) {
      failure = const ApiFailure(
        code: 'transfer_intent_expired',
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
        state == NajmBaharTransferState.confirmed) {
      return;
    }

    final frozen = _intent!;
    final generation = _generation;
    try {
      final result = await _repository.reconcileTransfer(frozen);
      if (!_canPublish(generation)) return;
      receipt = result;
      failure = null;
      state = NajmBaharTransferState.confirmed;
      notifyListeners();
      await _refreshAfterKnownSuccess();
    } on ApiFailure catch (error) {
      if (!_canPublish(generation)) return;
      if (_isSessionFailure(error)) {
        invalidateSession();
        return;
      }
      if (error.code == 'not_found') {
        state = NajmBaharTransferState.outcomeUnknown;
        failure = null;
        notifyListeners();
        return;
      }
      failure = error;
      state = NajmBaharTransferState.outcomeUnknown;
      notifyListeners();
    }
  }

  void invalidateSession() {
    if (_disposed || _sessionInvalidated) return;
    _sessionInvalidated = true;
    _generation++;
    capability = null;
    destination = null;
    receipt = null;
    failure = const ApiFailure(
      code: 'session_changed',
      message: '',
      retryable: false,
      httpStatus: 401,
    );
    _selectedSource = null;
    _reviewAmountGol = null;
    _reviewDescription = null;
    _intent = null;
    _intentStartedAt = null;
    state = NajmBaharTransferState.loadingCapability;
    _onSessionInvalidated?.call();
    notifyListeners();
  }

  Future<void> _submitFrozenIntent() async {
    final frozen = _intent!;
    final generation = _generation;
    state = NajmBaharTransferState.submitting;
    failure = null;
    notifyListeners();

    try {
      final result = await _repository.sendTransfer(frozen);
      if (!_canPublish(generation)) return;
      receipt = result;
      failure = null;
      state = NajmBaharTransferState.confirmed;
      notifyListeners();
      await _refreshAfterKnownSuccess();
    } on ApiFailure catch (error) {
      if (!_canPublish(generation)) return;
      if (state == NajmBaharTransferState.confirmed) return;
      failure = error;

      if (_isSessionFailure(error)) {
        invalidateSession();
      } else if (error.code == 'bootstrap_unavailable') {
        _intent = null;
        _intentStartedAt = null;
        state = NajmBaharTransferState.ready;
        notifyListeners();
      } else if (_isUnknownOutcome(error)) {
        state = NajmBaharTransferState.outcomeUnknown;
        notifyListeners();
      } else {
        _intent = null;
        _intentStartedAt = null;
        state = NajmBaharTransferState.definiteRejected;
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
      // A confirmed transfer remains authoritative if a display refresh fails.
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
    return 'transfer-$now-$entropy';
  }
}
