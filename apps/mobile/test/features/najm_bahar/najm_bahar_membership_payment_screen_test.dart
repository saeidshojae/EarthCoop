import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:dio/dio.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_membership_payment_controller.dart';
import 'package:earthcoop_mobile/features/najm_bahar/najm_bahar_membership_payment_section.dart';
import 'najm_bahar_repository_test.dart' as f;
import 'najm_bahar_membership_payment_repository_test.dart' as p;
import 'najm_bahar_policy_test.dart' as policy;

Future<void> show(WidgetTester tester, MembershipPaymentController c, {double scale=1}) async {
  await tester.pumpWidget(MaterialApp(home:Directionality(textDirection:TextDirection.rtl,
    child:MediaQuery(data:MediaQueryData(textScaler:TextScaler.linear(scale)),
      child:Scaffold(body:SingleChildScrollView(child:NajmBaharMembershipPaymentSection(controller:c)))))));
  await tester.pumpAndSettle();
}
void main(){
  testWidgets('both buckets appear and opening or cancelling never POSTs',(tester)async{
    final a=f.BoundaryAdapter((_)=>f.envelope(p.fee()));
    final c=MembershipPaymentController(f.repository(a));addTearDown(c.dispose);await c.prepare();await show(tester,c);
    expect(find.text('بهار کمرنگ'),findsOneWidget);expect(find.text('بهار فعال'),findsOneWidget);
    await tester.tap(find.byKey(const Key('membership-review')));await tester.pumpAndSettle();
    expect(find.text('تأیید و پرداخت'),findsOneWidget);
    await tester.tap(find.byKey(const Key('membership-cancel')));await tester.pumpAndSettle();
    expect(a.requests.where((r)=>r.method=='POST'),isEmpty);
  });
  testWidgets('explicit confirmation pays once and retains exact source',(tester)async{
    final a=f.BoundaryAdapter((r)=>f.envelope(r.method=='GET'?p.fee():p.receipt()));
    final c=MembershipPaymentController(f.repository(a));addTearDown(c.dispose);await c.prepare();await show(tester,c);
    await tester.tap(find.byKey(const Key('membership-review')));await tester.pumpAndSettle();
    expect(find.textContaining('NB-7'),findsWidgets);
    await tester.tap(find.byKey(const Key('membership-pay')));await tester.pumpAndSettle();
    expect(a.requests.where((r)=>r.method=='POST').length,1);expect(c.receipt?.hasPaid,true);
  });
  testWidgets('unknown result freezes selection and reconciliation never POSTs',(tester)async{
    final a=f.BoundaryAdapter((r){if(r.method=='GET')return f.envelope(p.fee());
      throw DioException(requestOptions:r,type:DioExceptionType.receiveTimeout);});
    final c=MembershipPaymentController(f.repository(a));addTearDown(c.dispose);await c.prepare();await show(tester,c);
    await tester.tap(find.byKey(const Key('membership-review')));await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('membership-pay')));await tester.pumpAndSettle();
    expect(find.text('نتیجهٔ پرداخت هنوز مشخص نیست'),findsOneWidget);
    expect(find.byKey(const Key('membership-review')),findsNothing);
    await tester.tap(find.byKey(const Key('membership-reconcile')));await tester.pumpAndSettle();
    expect(a.requests.where((r)=>r.method=='POST').length,1);
  });
  testWidgets('old server contract stays read only',(tester)async{
    final a=f.BoundaryAdapter((_)=>f.envelope({...policy.fee(),'has_paid':false}));
    final c=MembershipPaymentController(f.repository(a));addTearDown(c.dispose);await c.prepare();await show(tester,c);
    expect(find.byKey(const Key('membership-review')),findsNothing);
    expect(a.requests.where((r)=>r.method=='POST'),isEmpty);
  });
  testWidgets('narrow RTL and enlarged exact monetary values do not overflow',(tester)async{
    tester.view.physicalSize=const Size(320,900);tester.view.devicePixelRatio=1;
    addTearDown(tester.view.resetPhysicalSize);addTearDown(tester.view.resetDevicePixelRatio);
    final huge={...p.fee(),'fee_gol':9000000000000001,'breakdown':{
      'operations_salary_gol':9000000000000001,'central_insurance_gol':0,'money_destruction_gol':0},
      'payment_sources':[{...p.source(),'active_available_gol':9000000000000001,
        'dim_available_gol':9000000000000001}]};
    final a=f.BoundaryAdapter((_)=>f.envelope(huge));
    final c=MembershipPaymentController(f.repository(a));addTearDown(c.dispose);await c.prepare();await show(tester,c,scale:2);
    expect(tester.takeException(),isNull);
  });
}
