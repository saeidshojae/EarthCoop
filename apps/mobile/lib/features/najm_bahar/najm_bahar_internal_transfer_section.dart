import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_error.dart';
import 'najm_bahar_dto.dart';
import 'najm_bahar_internal_transfer_controller.dart';
import 'najm_bahar_internal_transfer_dto.dart';

class NajmBaharInternalTransferSection extends StatefulWidget {
  const NajmBaharInternalTransferSection({
    super.key,
    required this.controller,
  });

  final NajmBaharInternalTransferController controller;

  @override
  State<NajmBaharInternalTransferSection> createState() =>
      _NajmBaharInternalTransferSectionState();
}

class _NajmBaharInternalTransferSectionState
    extends State<NajmBaharInternalTransferSection> {
  final _createName = TextEditingController();
  final _renameName = TextEditingController();
  final _amount = TextEditingController();
  final _description = TextEditingController();

  int? _renameSubId;
  NajmBaharInternalAccountRef? _source;
  NajmBaharInternalAccountRef? _destination;
  String _bucket = 'active';

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_changed);
  }

  @override
  void dispose() {
    widget.controller.removeListener(_changed);
    _createName.dispose();
    _renameName.dispose();
    _amount.dispose();
    _description.dispose();
    super.dispose();
  }

  void _changed() {
    if (mounted) setState(() {});
  }

  List<NajmBaharInternalAccountRef> _accounts(
    NajmBaharSubAccountSnapshot snapshot,
  ) =>
      <NajmBaharInternalAccountRef>[snapshot.main, ...snapshot.subaccounts];

  @override
  Widget build(BuildContext context) {
    final c = widget.controller;
    if (c.state == NajmBaharInternalTransferState.loading &&
        c.snapshot == null) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('حساب‌های فرعی من',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              SizedBox(height: 12),
              LinearProgressIndicator(),
            ],
          ),
        ),
      );
    }
    if (c.state == NajmBaharInternalTransferState.outcomeUnknown) {
      return _unknown(c);
    }
    if (c.state == NajmBaharInternalTransferState.confirmed &&
        c.receipt != null) {
      return _confirmed(c);
    }
    if (c.state == NajmBaharInternalTransferState.reviewing) {
      return _review(c);
    }
    if (c.state == NajmBaharInternalTransferState.submitting) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('انتقال داخلی',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              SizedBox(height: 12),
              LinearProgressIndicator(),
              SizedBox(height: 12),
              Text('در حال ثبت همان درخواست انتقال داخلی…'),
            ],
          ),
        ),
      );
    }
    if (c.state == NajmBaharInternalTransferState.definiteRejected) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'انتقال داخلی انجام نشد. موجودی و حساب‌ها باید دوباره دریافت شوند.',
                style: TextStyle(fontWeight: FontWeight.bold),
              ),
              if (c.failure != null) ...[
                const SizedBox(height: 8),
                Text(_failureText(c.failure!)),
              ],
              const SizedBox(height: 12),
              OutlinedButton(
                key: const Key('internal-reload'),
                onPressed: () => unawaited(c.prepare()),
                child: const Text('دریافت دوباره حساب‌ها'),
              ),
            ],
          ),
        ),
      );
    }

    final snapshot = c.snapshot;
    if (snapshot == null) return const SizedBox.shrink();
    return _ready(c, snapshot);
  }

  Widget _ready(
    NajmBaharInternalTransferController c,
    NajmBaharSubAccountSnapshot snapshot,
  ) {
    final accounts = _accounts(snapshot);
    _source ??= snapshot.main;
    if (_destination == null && snapshot.subaccounts.isNotEmpty) {
      _destination = snapshot.subaccounts.first;
    }
    final source = _source;
    final destination = _destination;
    final available = source == null
        ? 0
        : (_bucket == 'active'
            ? source.activeAvailableGol
            : source.dimAvailableGol);
    final amountGol = _parseBaharToGol(_amount.text);
    final canReview = source != null &&
        destination != null &&
        source.accountNumber != destination.accountNumber &&
        amountGol != null &&
        amountGol > 0 &&
        amountGol <= available;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('حساب‌های فرعی من',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            const Text(
              'جابجایی داخلی فقط بین حساب‌های خودتان انجام می‌شود و مالکیت کل بهار شما را تغییر نمی‌دهد.',
            ),
            const SizedBox(height: 12),
            _summary(snapshot.main, 'حساب اصلی'),
            for (final sub in snapshot.subaccounts) _summary(sub, 'حساب فرعی'),
            const Divider(height: 28),
            TextField(
              key: const Key('subaccount-create-name'),
              controller: _createName,
              maxLength: 80,
              decoration: const InputDecoration(
                labelText: 'نام حساب فرعی جدید (اختیاری)',
                border: OutlineInputBorder(),
              ),
            ),
            FilledButton.tonal(
              key: const Key('subaccount-create'),
              onPressed: () => unawaited(c.createSubAccount(
                _createName.text.trim().isEmpty ? null : _createName.text.trim(),
              )),
              child: const Text('ایجاد حساب فرعی'),
            ),
            if (snapshot.subaccounts.isNotEmpty) ...[
              const SizedBox(height: 16),
              DropdownButtonFormField<int>(
                key: const Key('subaccount-rename-select'),
                initialValue: _renameSubId,
                isExpanded: true,
                decoration: const InputDecoration(
                  labelText: 'حساب فرعی برای تغییر نام',
                  border: OutlineInputBorder(),
                ),
                items: [
                  for (final sub in snapshot.subaccounts)
                    DropdownMenuItem<int>(
                      value: sub.subAccountId,
                      child: Text(
                        sub.name + ' · ' + sub.accountNumber,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                ],
                onChanged: (value) => setState(() => _renameSubId = value),
              ),
              const SizedBox(height: 8),
              TextField(
                key: const Key('subaccount-rename-name'),
                controller: _renameName,
                maxLength: 80,
                decoration: const InputDecoration(
                  labelText: 'نام جدید',
                  border: OutlineInputBorder(),
                ),
                onChanged: (_) => setState(() {}),
              ),
              OutlinedButton(
                key: const Key('subaccount-rename'),
                onPressed: _renameSubId == null ||
                        _renameName.text.trim().isEmpty
                    ? null
                    : () => unawaited(c.renameSubAccount(
                          _renameSubId!,
                          _renameName.text.trim(),
                        )),
                child: const Text('ثبت نام جدید'),
              ),
            ],
            const Divider(height: 32),
            const Text('انتقال داخلی',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            DropdownButtonFormField<NajmBaharInternalAccountRef>(
              key: const Key('internal-source'),
              initialValue: source,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'مبدأ',
                border: OutlineInputBorder(),
              ),
              items: [
                for (final account in accounts)
                  DropdownMenuItem<NajmBaharInternalAccountRef>(
                    value: account,
                    child: Text(
                      account.name + ' · ' + account.accountNumber,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
              ],
              onChanged: (value) => setState(() => _source = value),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<NajmBaharInternalAccountRef>(
              key: const Key('internal-destination'),
              initialValue: destination,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'مقصد',
                border: OutlineInputBorder(),
              ),
              items: [
                for (final account in accounts)
                  DropdownMenuItem<NajmBaharInternalAccountRef>(
                    value: account,
                    child: Text(
                      account.name + ' · ' + account.accountNumber,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
              ],
              onChanged: (value) => setState(() => _destination = value),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              key: const Key('internal-bucket'),
              initialValue: _bucket,
              decoration: const InputDecoration(
                labelText: 'نوع موجودی',
                border: OutlineInputBorder(),
              ),
              items: const [
                DropdownMenuItem(value: 'active', child: Text('بهار فعال')),
                DropdownMenuItem(value: 'dim', child: Text('بهار کمرنگ')),
              ],
              onChanged: (value) {
                if (value != null) setState(() => _bucket = value);
              },
            ),
            const SizedBox(height: 12),
            TextField(
              key: const Key('internal-amount'),
              controller: _amount,
              keyboardType:
                  const TextInputType.numberWithOptions(decimal: true),
              textDirection: TextDirection.ltr,
              decoration: InputDecoration(
                labelText: 'مبلغ بهار',
                helperText: 'قابل جابه‌جایی از مبدأ: ' + formatGol(available),
                border: const OutlineInputBorder(),
              ),
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 12),
            TextField(
              key: const Key('internal-description'),
              controller: _description,
              maxLength: 500,
              decoration: const InputDecoration(
                labelText: 'توضیح (اختیاری)',
                border: OutlineInputBorder(),
              ),
            ),
            if (c.failure != null) ...[
              const SizedBox(height: 8),
              Text(_failureText(c.failure!)),
            ],
            const SizedBox(height: 12),
            FilledButton(
              key: const Key('internal-review'),
              onPressed: canReview
                  ? () => c.beginReview(
                        source: source,
                        destination: destination,
                        balanceBucket: _bucket,
                        amountGol: amountGol,
                        description: _description.text,
                      )
                  : null,
              child: const Text('بررسی و تأیید انتقال داخلی'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _summary(NajmBaharInternalAccountRef account, String label) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Text(
        label +
            ': ' +
            account.name +
            ' · ' +
            account.accountNumber +
            '\nفعال قابل استفاده: ' +
            formatGol(account.activeAvailableGol) +
            ' · کمرنگ قابل استفاده: ' +
            formatGol(account.dimAvailableGol),
      ),
    );
  }

  Widget _review(NajmBaharInternalTransferController c) {
    final intent = c.reviewIntent!;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('تأیید انتقال داخلی',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            Text('مبدأ: ' + intent.source.name + ' · ' + intent.source.accountNumber),
            Text('مقصد: ' +
                intent.destination.name +
                ' · ' +
                intent.destination.accountNumber),
            Text('نوع موجودی: ' +
                (intent.balanceBucket == 'active'
                    ? 'بهار فعال'
                    : 'بهار کمرنگ')),
            Text('مبلغ: ' + formatGol(intent.amountGol)),
            if (intent.description != null)
              Text('توضیح: ' + intent.description!),
            const SizedBox(height: 8),
            const Text(
              'این عملیات فقط جای پول شما را بین حساب‌های خودتان عوض می‌کند و مجموع مالکیت شما ثابت می‌ماند.',
            ),
            const SizedBox(height: 16),
            FilledButton(
              key: const Key('internal-confirm'),
              onPressed: () => unawaited(c.confirm()),
              child: const Text('تأیید و انتقال داخلی'),
            ),
            const SizedBox(height: 8),
            TextButton(
              key: const Key('internal-cancel'),
              onPressed: c.cancelReview,
              child: const Text('انصراف'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _unknown(NajmBaharInternalTransferController c) {
    final intent = c.reviewIntent;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('نتیجهٔ انتقال داخلی هنوز مشخص نیست',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            const Text(
              'برای جلوگیری از جابه‌جایی دوباره، ابتدا نتیجهٔ همان درخواست را بررسی کنید.',
            ),
            if (intent != null) ...[
              Text('مبدأ: ' + intent.source.accountNumber),
              Text('مقصد: ' + intent.destination.accountNumber),
              Text('مبلغ: ' + formatGol(intent.amountGol)),
            ],
            const SizedBox(height: 12),
            FilledButton.tonal(
              key: const Key('internal-reconcile'),
              onPressed: () => unawaited(c.reconcile()),
              child: const Text('بررسی نتیجه'),
            ),
            const SizedBox(height: 8),
            OutlinedButton(
              key: const Key('internal-retry'),
              onPressed: () => unawaited(c.retrySameIntent()),
              child: const Text('تلاش دوباره با همان درخواست'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _confirmed(NajmBaharInternalTransferController c) {
    final receipt = c.receipt!;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('انتقال داخلی با موفقیت ثبت شد',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            Text('مبلغ: ' + formatGol(receipt.transaction.amountGol)),
            Text('مبدأ: ' + receipt.source.name + ' · ' + receipt.source.accountNumber),
            Text('مقصد: ' +
                receipt.destination.name +
                ' · ' +
                receipt.destination.accountNumber),
            Text('شماره پیگیری: ' + receipt.transaction.trackingNumber,
                textDirection: TextDirection.ltr),
            const SizedBox(height: 12),
            OutlinedButton(
              key: const Key('internal-new'),
              onPressed: () {
                _amount.clear();
                _description.clear();
                unawaited(c.prepare());
              },
              child: const Text('انتقال داخلی جدید'),
            ),
          ],
        ),
      ),
    );
  }

  static int? _parseBaharToGol(String raw) {
    final value = raw.trim();
    if (!RegExp(r'^\d+(?:\.\d{1,2})?$').hasMatch(value)) return null;
    final parts = value.split('.');
    final whole = BigInt.tryParse(parts[0]);
    if (whole == null) return null;
    final fractionText = parts.length == 1 ? '00' : parts[1].padRight(2, '0');
    final fraction = BigInt.tryParse(fractionText);
    if (fraction == null) return null;
    final total = whole * BigInt.from(100) + fraction;
    if (total <= BigInt.zero || total > BigInt.from(0x7fffffffffffffff)) {
      return null;
    }
    return total.toInt();
  }

  static String _failureText(ApiFailure failure) => switch (failure.code) {
        'internal_transfer_terms_changed' =>
          'موجودی یا حساب‌های انتخاب‌شده تغییر کرده‌اند؛ دوباره بررسی کنید.',
        'insufficient_available_funds' =>
          'موجودی قابل استفادهٔ مبدأ برای این مبلغ کافی نیست.',
        'not_found' => 'یکی از حساب‌ها دیگر در دسترس نیست.',
        'unauthenticated' ||
        'session_changed' =>
          'برای ادامه دوباره وارد حساب شوید.',
        'bootstrap_unavailable' =>
          'اتصال امن فعلاً آماده نیست. پس از بازیابی دوباره تلاش کنید.',
        'network_error' => 'ارتباط با سرور کامل نشد.',
        _ => 'عملیات کامل نشد. دوباره وضعیت حساب‌ها را بررسی کنید.',
      };
}
