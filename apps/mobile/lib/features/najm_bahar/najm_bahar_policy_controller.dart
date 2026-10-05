import 'package:flutter/foundation.dart';
import '../../core/api/api_error.dart';
import 'najm_bahar_repository.dart';
import 'najm_bahar_policy_dto.dart';
class NajmBaharPolicyRead<T> {
 T? value; ApiFailure? failure; bool loading=false; DateTime? receivedAt; int _generation=0;
}
class NajmBaharPolicyController extends ChangeNotifier {
 NajmBaharPolicyController(this._repository,{Listenable? sessionChanges,VoidCallback? onSessionInvalidated}):_sessionChanges=sessionChanges,_onSessionInvalidated=onSessionInvalidated {
  _sessionChanges?.addListener(_scopeChanged);
 }
 final NajmBaharRepository _repository; final Listenable? _sessionChanges; final VoidCallback? _onSessionInvalidated;
 final activation=NajmBaharPolicyRead<NajmBaharActivationEligibility>();
 final membership=NajmBaharPolicyRead<NajmBaharMembershipFee>();
 bool _disposed=false,_invalid=false;
 void _scopeChanged() { if(!_repository.isCurrentSession) { invalidateSession(); } }
 void _publish() { if(!_disposed) { notifyListeners(); } }
 void invalidateSession() {
  if(_disposed || _invalid) { return; } _invalid=true;
  _clear(activation); _clear(membership); _publish(); _onSessionInvalidated?.call();
 }
 void _clear<T>(NajmBaharPolicyRead<T> state) {
  state._generation++; state.value=null; state.receivedAt=null; state.loading=false;
  state.failure=const ApiFailure(code:'session_changed',message:'',retryable:false);
 }
 Future<void> load() async { await Future.wait([refreshActivation(),refreshMembership()]); }
 Future<void> refreshActivation()=>_refresh(activation,_repository.activationEligibility);
 Future<void> refreshMembership()=>_refresh(membership,_repository.membershipFee);
 Future<void> _refresh<T>(NajmBaharPolicyRead<T> state,Future<T> Function() read) async {
  if(_disposed || _invalid) { return; } final generation=++state._generation;
  state.loading=true; state.failure=null; _publish();
  try {
   final value=await read(); if(_disposed || generation!=state._generation) { return; }
   state.value=value; state.receivedAt=DateTime.now();
  } catch(error) {
   if(_disposed || generation!=state._generation) { return; }
   state.failure=error is ApiFailure?error:const ApiFailure(code:'malformed_response',message:'',retryable:false);
   if(state.failure!.httpStatus==401 || state.failure!.code=='session_changed' || state.failure!.code=='unauthenticated') { invalidateSession(); }
  } finally {
   if(!_disposed && generation==state._generation) { state.loading=false; _publish(); }
  }
 }
 @override void dispose() {
  _sessionChanges?.removeListener(_scopeChanged); _disposed=true; activation._generation++; membership._generation++; super.dispose();
 }
}
