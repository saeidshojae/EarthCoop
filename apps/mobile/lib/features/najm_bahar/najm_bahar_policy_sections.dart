import 'dart:async';
import 'package:flutter/material.dart';
import '../../core/api/api_error.dart';
import 'najm_bahar_dto.dart';
import 'najm_bahar_policy_controller.dart';

class NajmBaharPolicySections extends StatefulWidget {
  const NajmBaharPolicySections({super.key, required this.controller});
  final NajmBaharPolicyController controller;
  @override
  State<NajmBaharPolicySections> createState() =>
      _NajmBaharPolicySectionsState();
}

class _NajmBaharPolicySectionsState extends State<NajmBaharPolicySections> {
  void _changed() {
    if (mounted) setState(() {});
  }

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_changed);
  }

  @override
  void didUpdateWidget(covariant NajmBaharPolicySections oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller.removeListener(_changed);
      widget.controller.addListener(_changed);
    }
  }

  @override
  void dispose() {
    widget.controller.removeListener(_changed);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = widget.controller;
    final activation = c.activation.value;
    final membership = c.membership.value;
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      _section('شرایط فعال‌سازی', c.activation,
          () => unawaited(c.refreshActivation()), [
        if (activation != null) ...[
          Text(activation.enabled
              ? 'فعال‌سازی از امتیاز مشارکت در دسترس است.'
              : 'فعال‌سازی فعلاً غیرفعال است.'),
          Text(
              'امتیاز مشارکت باقی‌مانده: ${activation.remainingPoints} امتیاز'),
          Text('${activation.pointsPerGol} امتیاز برای هر گل'),
          Text('امتیاز قابل تبدیل: ${activation.maxConvertiblePoints} امتیاز'),
          Text(
              'حداکثر قابل فعال‌سازی: ${formatGol(activation.maxActivationGol)}'),
        ],
      ]),
      _section('وضعیت حق عضویت', c.membership,
          () => unawaited(c.refreshMembership()), [
        if (membership != null) ...[
          Text(membership.hasPaid
              ? 'حق عضویت این دوره پرداخت شده است.'
              : 'حق عضویت این دوره پرداخت نشده است.'),
          Text('دورهٔ عضویت: ${membership.paymentYear} (میلادی)'),
          Text('حق عضویت: ${formatGol(membership.feeGol)}'),
          Text('عملیات و حقوق: ${formatGol(membership.operationsGol)}'),
          Text('بیمهٔ مرکزی: ${formatGol(membership.insuranceGol)}'),
          Text('امحای پول: ${formatGol(membership.destructionGol)}'),
          if (!membership.hasPaid) ...[
            Text(
                'شرط پرداخت از موجودی فعال: ${membership.canPayActive ? 'برقرار' : 'برقرار نیست'}'),
            Text(
                'شرط پرداخت از موجودی کمرنگ: ${membership.canPayDim ? 'برقرار' : 'برقرار نیست'}'),
          ],
        ],
      ]),
    ]);
  }

  Widget _section<T>(String title, NajmBaharPolicyRead<T> state,
          VoidCallback refresh, List<Widget> content) =>
      Card(
          child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(children: [
                      Expanded(
                          child: Semantics(
                              header: true,
                              child: Text(title,
                                  style: const TextStyle(
                                      fontSize: 20,
                                      fontWeight: FontWeight.bold)))),
                      IconButton(
                          tooltip: 'به‌روزرسانی $title',
                          onPressed: state.loading || _expired(state.failure)
                              ? null
                              : refresh,
                          icon: const Icon(Icons.refresh))
                    ]),
                    if (state.loading) const LinearProgressIndicator(),
                    if (state.failure != null) ...[
                      Text(_message(state.failure!)),
                      if (!_expired(state.failure))
                        TextButton(
                            onPressed: state.loading ? null : refresh,
                            child: const Text('تلاش دوباره')),
                    ],
                    ...content,
                    if (state.receivedAt != null)
                      Text(
                          'آخرین دریافت: ${_date(state.receivedAt!)} (میلادی)'),
                    if (state.failure != null && state.value != null)
                      const Text(
                          'این اطلاعات مربوط به آخرین دریافت موفق است و تازه نیست.'),
                  ])));

  bool _expired(ApiFailure? failure) =>
      failure?.code == 'session_changed' || failure?.code == 'unauthenticated';
  String _message(ApiFailure failure) => switch (failure.code) {
        'activation_disabled' => 'فعال‌سازی فعلاً غیرفعال است.',
        'not_found' => 'حساب نجم بهار یافت نشد.',
        'membership_fee_policy_invalid' =>
          'اطلاعات حق عضویت فعلاً قابل نمایش نیست.',
        'session_changed' ||
        'unauthenticated' =>
          'برای مشاهده، دوباره وارد حساب شوید.',
        'bootstrap_unavailable' =>
          'اتصال فعلاً آماده نیست. پس از بازیابی، دوباره تلاش کنید.',
        'malformed_response' => 'پاسخ قابل نمایش نیست. دوباره تلاش کنید.',
        _ => 'دریافت اطلاعات کامل نشد. دوباره تلاش کنید.',
      };
  String _date(DateTime value) {
    final d = value.toLocal();
    return '${d.year}/${d.month.toString().padLeft(2, '0')}/${d.day.toString().padLeft(2, '0')} ${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';
  }
}
