import 'package:flutter/foundation.dart';

import '../../core/api/api_error.dart';
import 'group_dto.dart';
import 'group_repository.dart';

enum GroupViewFailureKind { retryable, forbidden, nonRetryable }

class GroupViewFailure {
  const GroupViewFailure._(this.kind, this.message);

  const GroupViewFailure.retryable(String message)
      : this._(GroupViewFailureKind.retryable, message);

  const GroupViewFailure.forbidden(String message)
      : this._(GroupViewFailureKind.forbidden, message);

  const GroupViewFailure.nonRetryable(String message)
      : this._(GroupViewFailureKind.nonRetryable, message);

  final GroupViewFailureKind kind;
  final String message;

  bool get canRetry => kind == GroupViewFailureKind.retryable;
}

enum GroupsPhase { loading, empty, ready, failure }

class GroupsState {
  const GroupsState._({
    required this.phase,
    this.items = const <GroupDto>[],
    this.isStale = false,
    this.failure,
  });

  const GroupsState.loading() : this._(phase: GroupsPhase.loading);

  const GroupsState.empty() : this._(phase: GroupsPhase.empty);

  GroupsState.ready(List<GroupDto> items, {bool isStale = false})
      : this._(
          phase: GroupsPhase.ready,
          items: List<GroupDto>.unmodifiable(items),
          isStale: isStale,
        );

  const GroupsState.failure(GroupViewFailure failure)
      : this._(phase: GroupsPhase.failure, failure: failure);

  final GroupsPhase phase;
  final List<GroupDto> items;
  final bool isStale;
  final GroupViewFailure? failure;
}

enum GroupDetailPhase { loading, ready, failure }

class GroupDetailState {
  const GroupDetailState._({
    required this.phase,
    this.group,
    this.isStale = false,
    this.failure,
  });

  const GroupDetailState.loading() : this._(phase: GroupDetailPhase.loading);

  const GroupDetailState.ready(GroupDto group, {bool isStale = false})
      : this._(
          phase: GroupDetailPhase.ready,
          group: group,
          isStale: isStale,
        );

  const GroupDetailState.failure(GroupViewFailure failure)
      : this._(phase: GroupDetailPhase.failure, failure: failure);

  final GroupDetailPhase phase;
  final GroupDto? group;
  final bool isStale;
  final GroupViewFailure? failure;
}

class GroupsController extends ChangeNotifier {
  GroupsController(this._repository);

  final GroupRepository _repository;

  GroupsState state = const GroupsState.loading();

  Future<void> load() async {
    state = const GroupsState.loading();
    notifyListeners();
    try {
      final result = await _repository.list();
      state = result.value.isEmpty
          ? const GroupsState.empty()
          : GroupsState.ready(result.value, isStale: result.isStale);
    } on ApiFailure catch (failure) {
      state = GroupsState.failure(_mapFailure(failure));
    } catch (_) {
      state = const GroupsState.failure(
        GroupViewFailure.nonRetryable('امکان دریافت گروه‌ها وجود ندارد.'),
      );
    }
    notifyListeners();
  }
}

class GroupDetailController extends ChangeNotifier {
  GroupDetailController(this._repository, this.groupId);

  final GroupRepository _repository;
  final int groupId;

  GroupDetailState state = const GroupDetailState.loading();

  Future<void> load() async {
    state = const GroupDetailState.loading();
    notifyListeners();
    try {
      final result = await _repository.find(groupId);
      state = GroupDetailState.ready(result.value, isStale: result.isStale);
    } on ApiFailure catch (failure) {
      state = GroupDetailState.failure(_mapFailure(failure));
    } catch (_) {
      state = const GroupDetailState.failure(
        GroupViewFailure.nonRetryable('امکان دریافت این گروه وجود ندارد.'),
      );
    }
    notifyListeners();
  }
}

GroupViewFailure _mapFailure(ApiFailure failure) {
  if (failure.httpStatus == 401 || failure.httpStatus == 403) {
    return const GroupViewFailure.forbidden('دسترسی به این بخش مجاز نیست.');
  }
  if (failure.retryable) {
    return const GroupViewFailure.retryable('ارتباط برقرار نشد. دوباره تلاش کنید.');
  }
  return const GroupViewFailure.nonRetryable('امکان دریافت اطلاعات وجود ندارد.');
}
