import 'dart:async';
import 'package:flutter/material.dart';
import '../../core/api/api_error.dart';
import 'najm_bahar_controller.dart';
import 'najm_bahar_dto.dart';
import 'najm_bahar_policy_controller.dart';
import 'najm_bahar_policy_sections.dart';

class NajmBaharScreen extends StatefulWidget {
  const NajmBaharScreen(
      {super.key, required this.controller, this.policyController});
  final NajmBaharController controller;
  final NajmBaharPolicyController? policyController;
  @override
  State<NajmBaharScreen> createState() => _NajmBaharScreenState();
}

class _NajmBaharScreenState extends State<NajmBaharScreen> {
  void _changed() {
    if (mounted) setState(() {});
  }

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_changed);
  }

  @override
  void didUpdateWidget(covariant NajmBaharScreen oldWidget) {
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
    final account = c.account;
    return Directionality(
        textDirection: TextDirection.rtl,
        child: Scaffold(
          appBar: AppBar(title: const Text('نجم بهار')),
          body: SafeArea(
              child: SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Row(children: [
                          const Expanded(
                              child: Text('کیف پول',
                                  style: TextStyle(
                                      fontSize: 22,
                                      fontWeight: FontWeight.bold))),
                          IconButton(
                              tooltip: 'به‌روزرسانی حساب',
                              onPressed: c.accountLoading
                                  ? null
                                  : () => unawaited(c.refreshAccount()),
                              icon: const Icon(Icons.refresh))
                        ]),
                        if (c.accountLoading) const LinearProgressIndicator(),
                        if (c.accountFailure != null)
                          _error(c.accountFailure!,
                              () => unawaited(c.refreshAccount()),
                              account: true),
                        if (account != null) ...[
                          Text(account.name),
                          SelectableText(account.accountNumber,
                              textDirection: TextDirection.ltr),
                          Text(account.status == 1
                              ? 'حساب فعال'
                              : account.status == 0
                                  ? 'حساب غیرفعال'
                                  : 'وضعیت حساب نامشخص'),
                          if (c.receivedAt != null)
                            Text(
                                'آخرین دریافت: ${_date(c.receivedAt!)} (میلادی)'),
                          if (c.accountFailure != null)
                            const Text(
                                'این موجودی مربوط به آخرین دریافت موفق است و تازه نیست.'),
                          _balance('حساب اصلی', account.local),
                          _balance('مجموع حساب‌ها', account.aggregate),
                        ],
                        const SizedBox(height: 16),
                        Row(children: [
                          const Expanded(
                              child: Text('تاریخچهٔ تراکنش‌ها',
                                  style: TextStyle(
                                      fontSize: 20,
                                      fontWeight: FontWeight.bold))),
                          IconButton(
                              tooltip: 'به‌روزرسانی تاریخچه',
                              onPressed: c.historyLoading
                                  ? null
                                  : () => unawaited(c.refreshHistory()),
                              icon: const Icon(Icons.refresh))
                        ]),
                        if (c.historyLoading) const LinearProgressIndicator(),
                        if (c.historyFailure != null)
                          _error(
                              c.historyFailure!,
                              () => unawaited(c.historyFailureFromPagination
                                  ? c.loadMore()
                                  : c.refreshHistory())),
                        if (!c.historyLoading &&
                            c.historyFailure == null &&
                            c.transactions.isEmpty)
                          const Padding(
                              padding: EdgeInsets.all(16),
                              child: Text('تراکنشی یافت نشد.')),
                        for (final transaction in c.transactions)
                          _transaction(transaction),
                        if (c.hasMore)
                          OutlinedButton(
                              onPressed: c.historyLoading
                                  ? null
                                  : () => unawaited(c.loadMore()),
                              child: const Text('تراکنش‌های بیشتر')),
                        if (widget.policyController != null)
                          NajmBaharPolicySections(
                              controller: widget.policyController!),
                      ]))),
        ));
  }

  Widget _error(ApiFailure failure, VoidCallback retry,
      {bool account = false}) {
    final message = switch (failure.code) {
      'not_found' =>
        account ? 'حساب نجم بهار یافت نشد.' : 'تاریخچه در دسترس نیست.',
      'unauthenticated' ||
      'session_changed' =>
        'برای مشاهده، دوباره وارد حساب شوید.',
      'bootstrap_unavailable' =>
        'اتصال فعلاً آماده نیست. پس از بازیابی، دوباره تلاش کنید.',
      'malformed_response' => 'پاسخ قابل نمایش نیست. دوباره تلاش کنید.',
      _ => 'دریافت اطلاعات کامل نشد. دوباره تلاش کنید.',
    };
    return Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(message),
          if (failure.code != 'session_changed' &&
              failure.code != 'unauthenticated')
            TextButton(onPressed: retry, child: const Text('تلاش دوباره'))
        ]));
  }

  Widget _balance(String title, NajmBaharBalance balance) => Card(
      child: Padding(
          padding: const EdgeInsets.all(16),
          child:
              Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text(title, style: const TextStyle(fontWeight: FontWeight.bold)),
            Text('فعال: ${formatGol(balance.activeGol)}'),
            Text('کمرنگ قابل استفاده: ${formatGol(balance.dimAvailableGol)}'),
            Text('کمرنگ متعهد: ${formatGol(balance.dimCommittedGol)}'),
            Text('مجموع کمرنگ: ${formatGol(balance.dimTotalGol)}'),
            Text('موجودی کل: ${formatGol(balance.totalGol)}'),
          ])));
  Widget _transaction(NajmBaharTransaction value) {
    final direction = switch (value.direction) {
      'incoming' => 'دریافتی',
      'outgoing' => 'پرداختی',
      'internal' => 'داخلی',
      _ => 'سایر'
    };
    final status = switch (value.status) {
      'completed' => 'تکمیل شده',
      'pending' => 'در انتظار',
      'failed' => 'ناموفق',
      _ => value.status
    };
    final type = switch (value.type) {
      'transfer' => 'انتقال',
      'membership_fee' => 'حق عضویت',
      'activation' => 'فعال‌سازی',
      _ => value.type
    };
    final bucket = switch (value.balanceBucket) {
      'active' => 'فعال',
      'dim' => 'کمرنگ',
      'legacy' => 'سابق',
      _ => value.balanceBucket
    };
    final parsed =
        value.createdAt == null ? null : DateTime.tryParse(value.createdAt!);
    return Card(
        child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(value.trackingNumber, textDirection: TextDirection.ltr),
                  Text('$direction · $type · $status'),
                  Text('${formatGol(value.amountGol)} · $bucket'),
                  if (value.counterparty != null)
                    Text(
                        '${value.counterparty!.name} (${value.counterparty!.accountNumber})'),
                  if (value.description?.isNotEmpty == true)
                    Text(value.description!),
                  Text(parsed == null
                      ? 'تاریخ در دسترس نیست'
                      : '${_date(parsed)} (میلادی)'),
                ])));
  }

  String _date(DateTime value) {
    final d = value.toLocal();
    return '${d.year}/${d.month.toString().padLeft(2, '0')}/${d.day.toString().padLeft(2, '0')} ${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';
  }
}
