import 'dart:async';

import 'package:flutter/material.dart';

import 'najm_bahar_activation_controller.dart';
import 'najm_bahar_dto.dart';

class NajmBaharActivationSection extends StatefulWidget {
  const NajmBaharActivationSection({super.key, required this.controller});

  final NajmBaharActivationController controller;

  @override
  State<NajmBaharActivationSection> createState() =>
      _NajmBaharActivationSectionState();
}

class _NajmBaharActivationSectionState
    extends State<NajmBaharActivationSection> {
  final TextEditingController _points = TextEditingController();

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_refresh);
  }

  @override
  void didUpdateWidget(covariant NajmBaharActivationSection oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller.removeListener(_refresh);
      widget.controller.addListener(_refresh);
      _points.clear();
    }
  }

  void _refresh() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    widget.controller.removeListener(_refresh);
    _points.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = widget.controller;
    final terms = c.terms;
    final state = c.state;

    return Directionality(
      textDirection: TextDirection.rtl,
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'فعال‌سازی بهار از امتیاز مشارکت',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              const Text(
                'امتیاز مشارکت واجد شرایط، بخشی از بهار کمرنگ شما را فعال می‌کند؛ بهار جدیدی ایجاد نمی‌شود.',
              ),
              if (state == NajmBaharActivationState.loading) ...[
                const SizedBox(height: 12),
                const LinearProgressIndicator(),
              ] else if (state == NajmBaharActivationState.outcomeUnknown) ...[
                const SizedBox(height: 12),
                const Text(
                  'نتیجه درخواست هنوز قطعی نیست. درخواست مالی تازه‌ای ثبت نکنید.',
                  key: Key('activation-unknown-message'),
                ),
                if (c.failure != null) Text('کد وضعیت: ${c.failure!.code}'),
                OutlinedButton(
                  key: const Key('activation-reconcile'),
                  onPressed: () => unawaited(c.reconcile()),
                  child: const Text('استعلام نتیجه بدون ارسال دوباره'),
                ),
                OutlinedButton(
                  key: const Key('activation-retry-same'),
                  onPressed: () => unawaited(c.retrySameIntent()),
                  child: const Text('تلاش با همان درخواست قبلی'),
                ),
              ] else if (state == NajmBaharActivationState.submitting) ...[
                const SizedBox(height: 12),
                const LinearProgressIndicator(),
                const Text('در حال ثبت همان درخواست تأییدشده…'),
              ] else if (state == NajmBaharActivationState.confirmed &&
                  c.receipt != null) ...[
                const SizedBox(height: 12),
                Text(
                    'فعال‌سازی ${formatGol(c.receipt!.activatedGol)} انجام شد.'),
                SelectableText(
                  'شماره پیگیری: ${c.receipt!.transaction.trackingNumber}',
                  textDirection: TextDirection.ltr,
                ),
                OutlinedButton(
                  onPressed: () => unawaited(c.prepare()),
                  child: const Text('دریافت شرایط جدید'),
                ),
              ] else if (state == NajmBaharActivationState.reviewing &&
                  terms != null &&
                  c.reviewPoints != null) ...[
                const SizedBox(height: 12),
                const Text(
                  'بررسی نهایی درخواست',
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),
                Text('امتیاز مصرفی: ${c.reviewPoints}'),
                Text('نرخ تبدیل: ${terms.pointsPerGol} امتیاز برای هر گل'),
                Text(
                  'بهار کمرنگی که فعال می‌شود: ${formatGol(c.reviewPoints! ~/ terms.pointsPerGol)}',
                ),
                const Text('این عملیات باید با تأیید صریح شما انجام شود.'),
                FilledButton(
                  key: const Key('activation-confirm'),
                  onPressed: () => unawaited(c.confirm()),
                  child: const Text('تأیید و فعال‌سازی'),
                ),
                OutlinedButton(
                  onPressed: c.cancelReview,
                  child: const Text('بازگشت'),
                ),
              ] else if (state ==
                  NajmBaharActivationState.definiteRejected) ...[
                const SizedBox(height: 12),
                const Text('درخواست انجام نشد. شرایط تازه را دریافت کنید.'),
                if (c.failure != null) Text('کد وضعیت: ${c.failure!.code}'),
                OutlinedButton(
                  onPressed: () => unawaited(c.prepare()),
                  child: const Text('دریافت دوباره شرایط'),
                ),
              ] else if (terms == null) ...[
                const SizedBox(height: 12),
                const Text('شرایط فعال‌سازی در دسترس نیست.'),
                OutlinedButton(
                  onPressed: () => unawaited(c.prepare()),
                  child: const Text('تلاش دوباره'),
                ),
              ] else ...[
                const SizedBox(height: 12),
                Text('امتیاز قابل تبدیل: ${terms.remainingPoints}'),
                Text('نرخ تبدیل: ${terms.pointsPerGol} امتیاز برای هر گل'),
                Text('حداکثر امتیاز قابل مصرف: ${terms.maxActivationPoints}'),
                Text(
                  'بهار کمرنگ قابل فعال‌سازی: ${formatGol(terms.maxActivationGol)}',
                ),
                if (terms.maxActivationPoints == 0)
                  const Text(
                      'در حال حاضر امکان فعال‌سازی از امتیاز وجود ندارد.')
                else ...[
                  TextField(
                    key: const Key('activation-points-input'),
                    controller: _points,
                    keyboardType: TextInputType.number,
                    textDirection: TextDirection.ltr,
                    decoration: const InputDecoration(
                      labelText: 'تعداد امتیاز برای تبدیل',
                      border: OutlineInputBorder(),
                    ),
                    onChanged: (_) => setState(() {}),
                  ),
                  const SizedBox(height: 8),
                  FilledButton(
                    key: const Key('activation-review'),
                    onPressed: _eligiblePoints(terms.pointsPerGol,
                                terms.maxActivationPoints) ==
                            null
                        ? null
                        : () => c.beginReview(
                              _eligiblePoints(
                                terms.pointsPerGol,
                                terms.maxActivationPoints,
                              )!,
                            ),
                    child: const Text('بررسی شرایط و مبلغ'),
                  ),
                ],
              ],
            ],
          ),
        ),
      ),
    );
  }

  int? _eligiblePoints(int ratio, int maximum) {
    final value = int.tryParse(_points.text.trim());
    if (value == null || value <= 0 || value > maximum || value % ratio != 0) {
      return null;
    }
    return value;
  }
}
