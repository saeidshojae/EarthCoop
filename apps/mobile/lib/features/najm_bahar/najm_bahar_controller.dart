import 'package:flutter/foundation.dart';
import '../../core/api/api_error.dart';
import 'najm_bahar_dto.dart';
import 'najm_bahar_repository.dart';

class NajmBaharController extends ChangeNotifier {
  NajmBaharController(this._repository,
      {Listenable? sessionChanges, VoidCallback? onSessionInvalidated})
      : _sessionChanges = sessionChanges,
        _onSessionInvalidated = onSessionInvalidated {
    _sessionChanges?.addListener(_sessionChanged);
  }
  final Listenable? _sessionChanges;
  final VoidCallback? _onSessionInvalidated;
  bool _sessionInvalid = false;
  void _sessionChanged() {
    if (!_repository.isCurrentSession) {
      invalidateSession();
    }
  }

  void invalidateSession() {
    if (_disposed || _sessionInvalid) return;
    _sessionInvalid = true;
    _accountGeneration++;
    _historyGeneration++;
    account = null;
    transactions = const [];
    nextCursor = null;
    hasMore = false;
    receivedAt = null;
    accountLoading = false;
    historyLoading = false;
    const failure =
        ApiFailure(code: 'session_changed', message: '', retryable: false);
    accountFailure = failure;
    historyFailure = failure;
    _publish();
    _onSessionInvalidated?.call();
  }

  final NajmBaharRepository _repository;
  NajmBaharAccount? account;
  ApiFailure? accountFailure, historyFailure;
  bool accountLoading = false, historyLoading = false;
  bool historyFailureFromPagination = false;
  List<NajmBaharTransaction> transactions = const [];
  String? nextCursor;
  bool hasMore = false;
  DateTime? receivedAt;
  int _accountGeneration = 0, _historyGeneration = 0;
  bool _disposed = false;
  void _publish() {
    if (!_disposed) notifyListeners();
  }

  ApiFailure _failure(Object error) => error is ApiFailure
      ? error
      : const ApiFailure(
          code: 'malformed_response', message: '', retryable: false);
  void _clearIfSessionChanged(ApiFailure failure) {
    if (failure.httpStatus == 401 ||
        failure.code == 'session_changed' ||
        failure.code == 'unauthenticated') {
      invalidateSession();
    }
  }

  Future<void> load() async {
    await Future.wait([refreshAccount(), refreshHistory()]);
  }

  Future<void> refreshAccount() async {
    if (_disposed || _sessionInvalid) return;
    final generation = ++_accountGeneration;
    accountLoading = true;
    accountFailure = null;
    _publish();
    try {
      final value = await _repository.account();
      if (_disposed || generation != _accountGeneration) return;
      account = value;
      receivedAt = DateTime.now();
    } catch (error) {
      if (_disposed || generation != _accountGeneration) return;
      accountFailure = _failure(error);
      _clearIfSessionChanged(accountFailure!);
    } finally {
      if (!_disposed && generation == _accountGeneration) {
        accountLoading = false;
        _publish();
      }
    }
  }

  Future<void> refreshHistory() async {
    if (_disposed || _sessionInvalid) return;
    final generation = ++_historyGeneration;
    historyLoading = true;
    historyFailure = null;
    historyFailureFromPagination = false;
    _publish();
    try {
      final page = await _repository.history();
      if (_disposed || generation != _historyGeneration) return;
      _apply(page, append: false);
    } catch (error) {
      if (_disposed || generation != _historyGeneration) return;
      historyFailure = _failure(error);
      _clearIfSessionChanged(historyFailure!);
    } finally {
      if (!_disposed && generation == _historyGeneration) {
        historyLoading = false;
        _publish();
      }
    }
  }

  Future<void> loadMore() async {
    if (_disposed ||
        _sessionInvalid ||
        historyLoading ||
        !hasMore ||
        nextCursor == null) {
      return;
    }
    final generation = _historyGeneration;
    final cursor = nextCursor;
    historyLoading = true;
    historyFailure = null;
    historyFailureFromPagination = false;
    _publish();
    try {
      final page = await _repository.history(cursor: cursor);
      if (_disposed || generation != _historyGeneration) return;
      _apply(page, append: true);
    } catch (error) {
      if (_disposed || generation != _historyGeneration) return;
      historyFailure = _failure(error);
      historyFailureFromPagination = true;
      _clearIfSessionChanged(historyFailure!);
    } finally {
      if (!_disposed && generation == _historyGeneration) {
        historyLoading = false;
        _publish();
      }
    }
  }

  void _apply(NajmBaharHistoryPage page, {required bool append}) {
    final byId = <int, NajmBaharTransaction>{};
    for (final value in [
      ...(append ? transactions : <NajmBaharTransaction>[]),
      ...page.items
    ]) {
      byId.putIfAbsent(value.id, () => value);
    }
    transactions = List.unmodifiable(byId.values);
    nextCursor = page.nextCursor;
    hasMore = page.hasMore;
  }

  @override
  void dispose() {
    _sessionChanges?.removeListener(_sessionChanged);
    _disposed = true;
    _accountGeneration++;
    _historyGeneration++;
    super.dispose();
  }
}
