import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_error.dart';
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
  final _points = TextEditingController();

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_changed);
  }

  @override
  void didUpdateWidget(covariant NajmBaharActivationSection oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller.removeListener(_changed);
      widget.controller.addListener(_changed);
    }
  }

  @override
  void dispose() {
    widget.controller.removeListener(_changed);
    _points.dispose();
    super.dispose();
  }

  void _changed() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final c = widget.controller;
    final e = c.eligibility;

    if (c.state == NajmBaharActivationState.loading && e == null) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('فعال‌سازی بهار از امتیاز مشارکت',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              SizedBox(height: 12),
              LinearProgressIndicator(),
            ],
          ),
        ),
      );
    }

    if (c.state == NajmBaharActivationState.outcomeUnknown) return _unknown(c);
    if (c.state == NajmBaharActivationState.confirmed && c.receipt != null) {
      return _confirmed(c);
    }
    if (c.state == NajmBaharActivationState.reviewing) return _review(c);
    if (c.state == NajmBaharActivationState.submitting) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('فعال‌سازی بهار از امتیاز مشارکت',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              SizedBox(height: 12),
              LinearProgressIndicator(),
              SizedBox(height: 12),
              Text('در حال ثبت همان درخواست فعال‌سازی…'),
            ],
          ),
        ),
      );
    }

    if (c.state == NajmBaharActivationState.definiteRejected) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text('فعال‌سازی انجام نشد. شرایط باید دوباره دریافت شود.',
                  style: TextStyle(fontWeight: FontWeight.bold)),
              if (c.failure != null) ...[
                const SizedBox(height: 8),
                Text(_failureText(c.failure!)),
              ],
              const SizedBox(height: 12),
              OutlinedButton(
                key: const Key('activation-reload'),
                onPressed: () => unawaited(c.prepare()),
                child: const Text('دریافت دوباره شرایط فعال‌سازی'),
              ),
            ],
          ),
        ),
      );
    }

    if (e == null) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text('فعال‌سازی بهار از امتیاز مشارکت',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              if (c.failure != null) Text(_failureText(c.failure!)),
              const SizedBox(height: 8),
              OutlinedButton(
                onPressed: () => unawaited(c.prepare()),
                child: const Text('بررسی دوباره'),
              ),
            ],
          ),
        ),
      );
    }

    final points = int.tryParse(_points.text.trim());
    final valid = points != null &&
        points > 0 &&
        points % e.pointsPerGol == 0 &&
        points <= e.maxActivationPoints &&
        c.state == NajmBaharActivationState.ready;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('فعال‌سازی بهار از امتیاز مشارکت',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            const Text(
              'این کار پول جدید ایجاد نمی‌کند؛ بخشی از بهار کمرنگ موجود شما را با مصرف امتیاز مشارکت به بهار فعال تبدیل می‌کند.',
            ),
            const SizedBox(height: 12),
            Text('امتیاز مشارکت قابل تبدیل: ${e.remainingPoints}'),
            Text('نسبت تبدیل: ${e.pointsPerGol} امتیاز برای هر گل'),
            Text('حداکثر امتیاز قابل مصرف اکنون: ${e.maxActivationPoints}'),
            Text('حداکثر قابل فعال‌سازی: ${formatGol(e.maxActivationGol)}'),
            Text('بهار کمرنگ موجود: ${formatGol(e.dimAvailableGol)}'),
            Text('بهار فعال موجود: ${formatGol(e.activeGol)}'),
            const SizedBox(height: 12),
            TextField(
              key: const Key('activation-points-input'),
              controller: _points,
              keyboardType: TextInputType.number,
              textDirection: TextDirection.ltr,
              decoration: InputDecoration(
                labelText: 'امتیاز مشارکت برای فعال‌سازی',
                helperText:
                    'عدد باید مضرب دقیق ${e.pointsPerGol} و حداکثر ${e.maxActivationPoints} باشد.',
                border: const OutlineInputBorder(),
              ),
              onChanged: (_) => setState(() {}),
            ),
            if (_points.text.isNotEmpty && !valid) ...[
              const SizedBox(height: 6),
              Text(
                  'امتیاز باید مضرب دقیق ${e.pointsPerGol} و در محدودهٔ مجاز باشد.'),
            ],
            if (c.failure != null) ...[
              const SizedBox(height: 8),
              Text(_failureText(c.failure!)),
            ],
            const SizedBox(height: 12),
            FilledButton(
              key: const Key('activation-review'),
              onPressed: valid ? () => c.beginReview(points) : null,
              child: const Text('بررسی و تأیید فعال‌سازی'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _review(NajmBaharActivationController c) {
    final e = c.eligibility!;
    final points = c.reviewPoints!;
    final amount = c.reviewActivatedGol!;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('تأیید فعال‌سازی بهار',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            Text('امتیاز مصرفی: $points'),
            Text('مقدار فعال‌شونده: ${formatGol(amount)}'),
            Text('کمرنگ قبل: ${formatGol(e.dimAvailableGol)}'),
            Text('کمرنگ بعد: ${formatGol(e.dimAvailableGol - amount)}'),
            Text('فعال قبل: ${formatGol(e.activeGol)}'),
            Text('فعال بعد: ${formatGol(e.activeGol + amount)}'),
            Text('نسبت تبدیل: ${e.pointsPerGol} امتیاز برای هر گل'),
            const SizedBox(height: 8),
            const Text(
              'پس از تأیید، همین امتیاز و همین شرایط سیاست برای این درخواست ثابت می‌مانند.',
            ),
            const SizedBox(height: 16),
            FilledButton(
              key: const Key('activation-confirm'),
              onPressed: () => unawaited(c.confirm()),
              child: const Text('تأیید و فعال‌سازی'),
            ),
            const SizedBox(height: 8),
            TextButton(
              key: const Key('activation-cancel'),
              onPressed: c.cancelReview,
              child: const Text('انصراف'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _unknown(NajmBaharActivationController c) => Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text('نتیجهٔ فعال‌سازی هنوز مشخص نیست',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              const Text(
                'برای جلوگیری از مصرف دوبارهٔ امتیاز، ابتدا نتیجهٔ همان درخواست را بررسی کنید.',
              ),
              if (c.reviewPoints != null)
                Text('امتیاز ثابت‌شده: ${c.reviewPoints}'),
              if (c.reviewActivatedGol != null)
                Text('بهار فعال‌شونده: ${formatGol(c.reviewActivatedGol!)}'),
              const SizedBox(height: 12),
              FilledButton.tonal(
                key: const Key('activation-reconcile'),
                onPressed: () => unawaited(c.reconcile()),
                child: const Text('بررسی نتیجه'),
              ),
              const SizedBox(height: 8),
              OutlinedButton(
                key: const Key('activation-retry-same-intent'),
                onPressed: () => unawaited(c.retrySameIntent()),
                child: const Text('تلاش دوباره با همان درخواست'),
              ),
            ],
          ),
        ),
      );

  Widget _confirmed(NajmBaharActivationController c) {
    final receipt = c.receipt!;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('فعال‌سازی با موفقیت ثبت شد',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            Text('امتیاز مصرف‌شده: ${receipt.consumedPoints}'),
            Text('مقدار فعال‌شده: ${formatGol(receipt.activatedGol)}'),
            Text('شماره پیگیری: ${receipt.transaction.trackingNumber}',
                textDirection: TextDirection.ltr),
            const SizedBox(height: 12),
            OutlinedButton(
              key: const Key('activation-new'),
              onPressed: () {
                _points.clear();
                unawaited(c.prepare());
              },
              child: const Text('فعال‌سازی جدید'),
            ),
          ],
        ),
      ),
    );
  }

  static String _failureText(ApiFailure failure) => switch (failure.code) {
        'activation_disabled' =>
          'فعال‌سازی از امتیاز مشارکت طبق سیاست فعلی غیرفعال است.',
        'activation_terms_changed' =>
          'شرایط تبدیل تغییر کرده است؛ اطلاعات تازه را دوباره بررسی کنید.',
        'activation_not_eligible' =>
          'امتیاز یا ظرفیت فعلی برای این فعال‌سازی کافی نیست.',
        'insufficient_dim' =>
          'بهار کمرنگ قابل استفاده برای این فعال‌سازی کافی نیست.',
        'unauthenticated' ||
        'session_changed' =>
          'برای ادامه دوباره وارد حساب شوید.',
        'bootstrap_unavailable' =>
          'اتصال امن فعلاً آماده نیست. پس از بازیابی دوباره تلاش کنید.',
        'network_error' => 'ارتباط با سرور کامل نشد.',
        _ => 'عملیات فعال‌سازی کامل نشد. دوباره بررسی کنید.',
      };
}
