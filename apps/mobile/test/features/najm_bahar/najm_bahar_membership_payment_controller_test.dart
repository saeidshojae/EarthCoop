import 'dart:async';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_membership_payment_controller.dart';
import 'najm_bahar_repository_test.dart' as f;
import 'najm_bahar_membership_payment_repository_test.dart' as p;

Map<String,Object?> error(String code,{bool retryable=false}) => {
  'status':'error','data':null,'meta':{'api_version':'v1'},'request_id':'payment-test',
  'error':{'code':code,'message':'','retryable':retryable}};
Future<void> prepare(MembershipPaymentController c) async {
  await c.prepare();
  c.selectSource(c.terms!.paymentSources.first,'dim');
  c.beginConfirmation();
}
void main() {
  test('opening preparation and confirmation send no payment',() async {
    final a=f.BoundaryAdapter((_)=>f.envelope(p.fee()));
    final c=MembershipPaymentController(f.repository(a),keyFactory:()=>'test-key'); addTearDown(c.dispose);
    await prepare(c);
    expect(c.state,MembershipPaymentState.confirming);
    expect(a.requests.map((r)=>r.method),['GET']);
  });
  test('repeated confirm shares one pending POST and unpaid GET stays frozen',() async {
    final response=Completer<Map<String,Object?>>();
    final a=f.BoundaryAdapter((r)=>r.method=='POST'?response.future:f.envelope(p.fee()));
    final c=MembershipPaymentController(f.repository(a),keyFactory:()=>'test-key');addTearDown(c.dispose);
    await prepare(c); final first=c.confirm(); final second=c.confirm();
    await Future<void>.delayed(Duration.zero); await c.reconcile();
    expect(c.state,MembershipPaymentState.submitting);
    c.selectSource(c.terms!.paymentSources.last,'active');
    expect(a.requests.where((r)=>r.method=='POST').length,1);
    response.complete(f.envelope(p.receipt())); await Future.wait([first,second]);
    expect(c.receipt?.hasPaid,true);expect(c.state,MembershipPaymentState.confirmedPaid);
  });
  test('unknown result permits only same exact intent retry',() async {
    var fail=true;
    final a=f.BoundaryAdapter((r){
      if(r.method=='GET') return f.envelope(p.fee());
      if(fail) throw DioException(requestOptions:r,type:DioExceptionType.receiveTimeout);
      return f.envelope(p.receipt());});
    final c=MembershipPaymentController(f.repository(a),keyFactory:()=>'stable-key');addTearDown(c.dispose);
    await prepare(c);await c.confirm();expect(c.state,MembershipPaymentState.outcomeUnknown);
    c.selectSource(c.terms!.paymentSources.last,'active'); c.beginConfirmation(); await c.prepare();
    fail=false;await c.retrySameIntent();
    final posts=a.requests.where((r)=>r.method=='POST').toList();expect(posts.length,2);
    expect(posts[1].headers['Idempotency-Key'],posts[0].headers['Idempotency-Key']);
    expect(posts[1].data,posts[0].data);expect(c.receipt?.hasPaid,true);
  });
  test('paid reconciliation proves obligation without creating own receipt',() async {
    var paid=false;
    final a=f.BoundaryAdapter((r){if(r.method=='GET')return f.envelope({...p.fee(),'has_paid':paid});
      throw DioException(requestOptions:r,type:DioExceptionType.receiveTimeout);});
    final c=MembershipPaymentController(f.repository(a));addTearDown(c.dispose);
    await prepare(c);await c.confirm();paid=true;await c.reconcile();
    expect(c.obligationPaid,true);expect(c.receipt,isNull);expect(c.state,MembershipPaymentState.confirmedPaid);
  });
  test('different membership period cannot resolve unknown intent',() async {
    var rollover=false;
    final a=f.BoundaryAdapter((r){if(r.method=='GET')return f.envelope({...p.fee(),
      if(rollover)'payment_year':2026,if(rollover)'has_paid':true});
      throw DioException(requestOptions:r,type:DioExceptionType.receiveTimeout);});
    final c=MembershipPaymentController(f.repository(a));addTearDown(c.dispose);
    await prepare(c);await c.confirm();rollover=true;await c.reconcile();
    expect(c.state,MembershipPaymentState.outcomeUnknown);expect(c.obligationPaid,false);
  });
  test('confirmed success survives financial refresh failure',() async {
    final a=f.BoundaryAdapter((r)=>f.envelope(r.method=='GET'?p.fee():p.receipt()));
    final c=MembershipPaymentController(f.repository(a),refreshFinancialViews:()async=>throw StateError('refresh failed'));
    addTearDown(c.dispose);await prepare(c);await c.confirm();
    expect(c.state,MembershipPaymentState.confirmedPaid);expect(c.receipt?.hasPaid,true);
  });
  test('monotonic expired intent never POSTs again',() async {
    var elapsed=Duration.zero;
    final a=f.BoundaryAdapter((r){if(r.method=='GET')return f.envelope(p.fee());
      throw DioException(requestOptions:r,type:DioExceptionType.receiveTimeout);});
    final c=MembershipPaymentController(f.repository(a),elapsedSinceStart:()=>elapsed);addTearDown(c.dispose);
    await prepare(c);await c.confirm();elapsed=const Duration(hours:23);await c.retrySameIntent();
    expect(a.requests.where((r)=>r.method=='POST').length,1);
    expect(c.state,MembershipPaymentState.outcomeUnknown);
  });
  test('restart prepares GET and never replays a mutation',()async{
    final a=f.BoundaryAdapter((_)=>f.envelope(p.fee()));
    final first=MembershipPaymentController(f.repository(a));await prepare(first);first.dispose();
    final restarted=MembershipPaymentController(f.repository(a));addTearDown(restarted.dispose);await restarted.prepare();
    expect(a.requests.every((r)=>r.method=='GET'),true);
  });
  test('bootstrap pause during confirmation is recoverable and sends no POST',()async{
    var allowed=true;final a=f.BoundaryAdapter((_)=>f.envelope(p.fee()));
    final c=MembershipPaymentController(f.repository(a,allowed:()=>allowed));addTearDown(c.dispose);
    await prepare(c);allowed=false;await c.confirm();
    expect(a.requests.where((r)=>r.method=='POST'),isEmpty);expect(c.receipt,isNull);
    allowed=true;await c.prepare();expect(c.state,MembershipPaymentState.ready);
  });
  test('malformed 201 and request in progress are unknown outcomes',()async{
    for(final malformed in [true,false]){
      final a=f.BoundaryAdapter((r)=>r.method=='GET'?f.envelope(p.fee()):malformed?
        f.envelope({...p.receipt(),'has_paid':false}):error('request_in_progress',retryable:true),
        statusFor:(r)=>r.method=='GET'?200:malformed?201:409);
      final c=MembershipPaymentController(f.repository(a));await prepare(c);await c.confirm();
      expect(c.state,MembershipPaymentState.outcomeUnknown);c.dispose();
    }
  });
  test('already paid reconciles status without inventing own receipt',()async{
    var paid=false;
    final a=f.BoundaryAdapter((r){
      if(r.method=='GET')return f.envelope({...p.fee(),'has_paid':paid});
      paid=true;return error('already_paid');},statusFor:(r)=>r.method=='GET'?200:409);
    final c=MembershipPaymentController(f.repository(a));addTearDown(c.dispose);await prepare(c);await c.confirm();
    expect(c.state,MembershipPaymentState.confirmedPaid);expect(c.obligationPaid,true);expect(c.receipt,isNull);
    expect(a.requests.where((r)=>r.method=='POST').length,1);
  });
  test('401 clears financial state and invalidates siblings once',()async{
    var invalidations=0;
    final a=f.BoundaryAdapter((r)=>r.method=='GET'?f.envelope(p.fee()):error('malformed_response'),statusFor:(r)=>r.method=='GET'?200:401);
    final c=MembershipPaymentController(f.repository(a),onSessionInvalidated:()=>invalidations++);addTearDown(c.dispose);
    await prepare(c);await c.confirm();expect(c.terms,isNull);expect(c.receipt,isNull);expect(invalidations,1);
    await c.prepare();expect(a.requests.length,2);
  });
  test('logout and disposal suppress delayed payment publication',()async{
    for(final dispose in [false,true]){
      var current=true;final changes=ChangeNotifier(); final pending=Completer<Map<String,Object?>>();
      final a=f.BoundaryAdapter((r)=>r.method=='GET'?f.envelope(p.fee()):pending.future);
      final c=MembershipPaymentController(f.repository(a,current:()=>current),sessionChanges:changes);
      await prepare(c);final task=c.confirm();await Future<void>.delayed(Duration.zero);
      if(dispose){c.dispose();}else{current=false;changes.notifyListeners();}
      pending.complete(f.envelope(p.receipt()));await task;
      expect(c.receipt,isNull);if(!dispose){expect(c.terms,isNull);c.dispose();}changes.dispose();
    }
  });
}
