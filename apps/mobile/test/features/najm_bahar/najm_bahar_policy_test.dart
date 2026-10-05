import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_policy_controller.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_policy_sections.dart';
import 'najm_bahar_repository_test.dart' as f;

Map<String, Object?> eligibility() => {
 'enabled': true, 'source': 'participation', 'remaining_convertible_points': 350,
 'conversion_ratio_points_per_gol': 100, 'max_convertible_points': 300,
 'max_activation_gol': 3, 'dim_available_gol': 101, 'active_gol': 200,
 'policy_version': null, 'policy_source': 'legacy_settings'};
Map<String, Object?> fee() => {
 'has_paid': true, 'payment_year': 2025, 'fee_gol': 1200,
 'breakdown': {'operations_salary_gol': 600, 'central_insurance_gol': 300, 'money_destruction_gol': 300},
 'can_pay_from_dim': false, 'can_pay_from_active': true, 'default_payment_source': 'active',
 'policy_version_id': null, 'balance': {'local': f.balance(), 'aggregate': f.balance()}};
Map<String, Object?> disabled() => {
 'status': 'error', 'data': null, 'request_id': 'policy-test', 'meta': {'api_version': 'v1'},
 'error': {'code': 'activation_disabled', 'message': 'disabled', 'retryable': false}};
void main() {
 test('policy reads preserve points units and only emit GET', () async {
  final adapter=f.BoundaryAdapter((r)=>f.envelope(r.path.endsWith('eligibility')?eligibility():fee()));
  final api=f.repository(adapter);
  final e=await api.activationEligibility(); final m=await api.membershipFee();
  expect(e.pointsPerGol,100); expect(e.remainingPoints,350); expect(e.maxActivationGol,3);
  expect(m.paymentYear,2025); expect(m.feeGol,1200); expect(m.hasPaid,true);
  expect(adapter.requests.map((r)=>r.method),['GET','GET']);
  expect(adapter.requests.map((r)=>r.path),['/najm-bahar/activation/eligibility','/najm-bahar/membership-fee']);
 });
 test('invalid policy integers and inconsistent fee breakdown reject', () async {
  final api=f.repository(f.BoundaryAdapter((r)=>f.envelope(r.path.endsWith('eligibility')
   ? {...eligibility(),'conversion_ratio_points_per_gol':0}
   : {...fee(),'fee_gol':1201})));
  await expectLater(api.activationEligibility(),throwsA(anything));
  await expectLater(api.membershipFee(),throwsA(anything));
 });
 test('disabled activation does not hide successful membership fee', () async {
  final adapter=f.BoundaryAdapter((r)=>r.path.endsWith('eligibility')?disabled():f.envelope(fee()),statusFor:(r)=>r.path.endsWith('eligibility')?403:200);
  final c=NajmBaharPolicyController(f.repository(adapter)); addTearDown(c.dispose);
  await c.load();
  expect(c.activation.failure?.code,'activation_disabled'); expect(c.membership.value?.hasPaid,true);
 });
 test('temporary blockage retains dated policy and permits retry', () async {
  var allowed=true;
  final c=NajmBaharPolicyController(f.repository(f.BoundaryAdapter((r)=>f.envelope(r.path.endsWith('eligibility')?eligibility():fee())),allowed:()=>allowed));
  addTearDown(c.dispose); await c.load(); final date=c.membership.receivedAt;
  allowed=false; await c.load(); expect(c.membership.value?.feeGol,1200); expect(c.membership.receivedAt,date);
  expect(c.membership.failure?.code,'bootstrap_unavailable'); allowed=true; await c.load(); expect(c.membership.failure,isNull);
 });
 test('late policy responses after scope change clear both sections', () async {
  var current=true; final pending=Completer<Map<String,Object?>>();
  final c=NajmBaharPolicyController(f.repository(f.BoundaryAdapter((_)=>pending.future),current:()=>current));
  addTearDown(c.dispose); final result=c.load(); current=false; pending.complete(f.envelope(fee())); await result;
  expect(c.activation.value,isNull); expect(c.membership.value,isNull); expect(c.membership.failure?.code,'session_changed');
 });
 testWidgets('policy sections show server units and membership year without mutation buttons',(tester) async {
  final c=NajmBaharPolicyController(f.repository(f.BoundaryAdapter((r)=>f.envelope(r.path.endsWith('eligibility')?eligibility():fee()))));
  addTearDown(c.dispose); await tester.runAsync(()=>c.load());
  await tester.pumpWidget(MaterialApp(home:Scaffold(body:SingleChildScrollView(child:NajmBaharPolicySections(controller:c)))));
  expect(find.text('۱۰۰ امتیاز برای هر گل'),findsNothing);
  expect(find.text('100 امتیاز برای هر گل'),findsOneWidget);
  expect(find.text('حداکثر قابل فعال‌سازی: 3 گل'),findsOneWidget);
  expect(find.text('دورهٔ عضویت: 2025 (میلادی)'),findsOneWidget);
  expect(find.text('حق عضویت این دوره پرداخت شده است.'),findsOneWidget);
  expect(find.text('پرداخت'),findsNothing); expect(find.text('فعال‌سازی'),findsNothing);
 },timeout:const Timeout(Duration(seconds:20)));
}
