import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_error.dart';
import 'najm_bahar_dto.dart';
import 'najm_bahar_transfer_controller.dart';
import 'najm_bahar_transfer_dto.dart';

class NajmBaharTransferSection extends StatefulWidget {
  const NajmBaharTransferSection({
    super.key,
    required this.controller,
  });

  final NajmBaharTransferController controller;

  @override
  State<NajmBaharTransferSection> createState() =>
      _NajmBaharTransferSectionState();
}

class _NajmBaharTransferSectionState extends State<NajmBaharTransferSection> {
  final _destination = TextEditingController();
  final _amount = TextEditingController();
  final _description = TextEditingController();

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_changed);
    _ensureDefaultSource();
  }

  @override
  void didUpdateWidget(covariant NajmBaharTransferSection oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller.removeListener(_changed);
      widget.controller.addListener(_changed);
      _ensureDefaultSource();
    }
  }

  @override
  void dispose() {
    widget.controller.removeListener(_changed);
    _destination.dispose();
    _amount.dispose();
    _description.dispose();
    super.dispose();
  }

  void _changed() {
    if (!mounted) return;
    _ensureDefaultSource();
    setState(() {});
  }

  void _ensureDefaultSource() {
    final c = widget.controller;
    final capability = c.capability;
    if (c.state != NajmBaharTransferState.ready ||
        capability?.externalTransferEnabled != true ||
        c.selectedSource != null) {
      return;
    }
    final eligible =
        capability!.sources.where((source) => source.canTransferActive);
    if (eligible.isNotEmpty) {
      c.selectSource(eligible.first);
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = widget.controller;
    final capability = c.capability;

    if (c.state == NajmBaharTransferState.loadingCapability &&
        capability == null) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('انتقال بهار',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              SizedBox(height: 12),
              LinearProgressIndicator(),
            ],
          ),
        ),
      );
    }

    if (c.state == NajmBaharTransferState.outcomeUnknown) return _unknown(c);
    if (c.state == NajmBaharTransferState.confirmed && c.receipt != null) {
      return _confirmed(c);
    }
    if (c.state == NajmBaharTransferState.reviewing) return _review(c);

    if (c.state == NajmBaharTransferState.submitting) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('انتقال بهار',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              SizedBox(height: 12),
              LinearProgressIndicator(),
              SizedBox(height: 12),
              Text('در حال ثبت همان درخواست انتقال…'),
            ],
          ),
        ),
      );
    }

    if (c.state == NajmBaharTransferState.definiteRejected) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'انتقال انجام نشد. شرایط انتقال باید دوباره دریافت شود.',
                style: TextStyle(fontWeight: FontWeight.bold),
              ),
              if (c.failure != null) ...[
                const SizedBox(height: 8),
                Text(_failureText(c.failure!)),
              ],
              const SizedBox(height: 12),
              OutlinedButton(
                key: const Key('transfer-reload'),
                onPressed: () => unawaited(c.prepare()),
                child: const Text('دریافت دوباره شرایط انتقال'),
              ),
            ],
          ),
        ),
      );
    }

    if (capability == null) return const SizedBox.shrink();
    if (!capability.externalTransferEnabled) return _locked(c, capability);
    return _selection(c, capability);
  }

  Widget _locked(
    NajmBaharTransferController c,
    NajmBaharTransferCapability capability,
  ) {
    final reason = switch (capability.disabledReason) {
      'threshold_not_met' =>
        'انتقال بهار بین مالکان مستقل هنوز طبق آستانهٔ فعلی نجم بهار باز نشده است.',
      'no_eligible_source' =>
        'حساب فرعی فعالی با بهار فعالِ قابل انتقال در دسترس نیست.',
      'policy_disabled' => 'انتقال بیرونی طبق سیاست فعلی نجم بهار غیرفعال است.',
      _ => 'انتقال بیرونی در حال حاضر در دسترس نیست.',
    };
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('انتقال بهار',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            Text(reason),
            const SizedBox(height: 8),
            const Text(
              'انتقال بیرونی فقط با بهار فعال انجام می‌شود؛ بهار کمرنگ به مالک مستقل قابل انتقال نیست.',
            ),
            if (c.failure != null) ...[
              const SizedBox(height: 8),
              Text(_failureText(c.failure!)),
            ],
            const SizedBox(height: 12),
            OutlinedButton(
              onPressed: () => unawaited(c.prepare()),
              child: const Text('بررسی دوباره'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _selection(
    NajmBaharTransferController c,
    NajmBaharTransferCapability capability,
  ) {
    final sources = capability.sources
        .where((source) => source.canTransferActive)
        .toList(growable: false);
    final selected = c.selectedSource;
    final normalizedInput = _normalizeAccount(_destination.text);
    final destinationFresh = c.destination != null &&
        c.destination!.accountNumber == normalizedInput;
    final amountGol = _parseBaharToGol(_amount.text);
    final canReview = selected != null &&
        destinationFresh &&
        amountGol != null &&
        amountGol > 0 &&
        amountGol <= selected.activeAvailableGol &&
        c.state == NajmBaharTransferState.ready;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('انتقال بهار',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            const Text(
              'ارسال بیرونی در این نسخه فقط از یک حساب فرعی خودتان و فقط با بهار فعال انجام می‌شود.',
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<NajmBaharTransferSource>(
              key: ValueKey<String>(
                'transfer-source-${selected?.accountNumber ?? 'none'}',
              ),
              initialValue: selected,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'حساب فرعی مبدأ',
                border: OutlineInputBorder(),
              ),
              items: [
                for (final source in sources)
                  DropdownMenuItem<NajmBaharTransferSource>(
                    value: source,
                    child: Text(
                      '${source.name} · ${source.accountNumber} · ${formatGol(source.activeAvailableGol)} فعال قابل انتقال',
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
              ],
              onChanged: (source) {
                if (source != null) c.selectSource(source);
              },
            ),
            const SizedBox(height: 12),
            TextField(
              key: const Key('transfer-destination-input'),
              controller: _destination,
              textDirection: TextDirection.ltr,
              autocorrect: false,
              enableSuggestions: false,
              decoration: const InputDecoration(
                labelText: 'شماره حساب فرعی مقصد',
                hintText: '1000000011-002',
                border: OutlineInputBorder(),
              ),
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 8),
            FilledButton.tonal(
              key: const Key('transfer-preview-destination'),
              onPressed: selected == null ||
                      _destination.text.trim().isEmpty ||
                      c.state == NajmBaharTransferState.resolvingDestination
                  ? null
                  : () => unawaited(c.resolveDestination(_destination.text)),
              child: c.state == NajmBaharTransferState.resolvingDestination
                  ? const SizedBox(
                      height: 18,
                      width: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('بررسی مقصد'),
            ),
            if (destinationFresh) ...[
              const SizedBox(height: 8),
              Text(
                'مقصد تأییدشده: ${c.destination!.ownerDisplayName} · ${c.destination!.name}',
              ),
              SelectableText(c.destination!.accountNumber,
                  textDirection: TextDirection.ltr),
            ],
            if (c.failure != null) ...[
              const SizedBox(height: 8),
              Text(_failureText(c.failure!)),
            ],
            const SizedBox(height: 12),
            TextField(
              key: const Key('transfer-amount-input'),
              controller: _amount,
              keyboardType:
                  const TextInputType.numberWithOptions(decimal: true),
              textDirection: TextDirection.ltr,
              decoration: InputDecoration(
                labelText: 'مبلغ بهار',
                helperText: selected == null
                    ? 'ابتدا حساب مبدأ را انتخاب کنید.'
                    : 'حداکثر: ${formatGol(selected.activeAvailableGol)}',
                border: const OutlineInputBorder(),
              ),
              onChanged: (_) => setState(() {}),
            ),
            if (_amount.text.isNotEmpty && amountGol == null)
              const Padding(
                padding: EdgeInsets.only(top: 6),
                child: Text('مبلغ را با حداکثر دو رقم اعشار وارد کنید.'),
              ),
            const SizedBox(height: 12),
            TextField(
              key: const Key('transfer-description-input'),
              controller: _description,
              maxLength: 500,
              decoration: const InputDecoration(
                labelText: 'توضیح (اختیاری)',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 4),
            const Text('بهار کمرنگ در انتقال بیرونی قابل انتخاب نیست.'),
            const SizedBox(height: 12),
            FilledButton(
              key: const Key('transfer-review'),
              onPressed: canReview
                  ? () => c.beginReview(
                        amountGol: amountGol,
                        description: _description.text,
                      )
                  : null,
              child: const Text('بررسی و تأیید انتقال'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _review(NajmBaharTransferController c) {
    final source = c.selectedSource!;
    final destination = c.destination!;
    final amount = c.reviewAmountGol!;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('تأیید انتقال بهار',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            const Text('منبع: بهار فعال'),
            Text('مبدأ: ${source.name} · ${source.accountNumber}'),
            Text(
              'موجودی فعال قابل انتقال هنگام بررسی: ${formatGol(source.activeAvailableGol)}',
            ),
            Text('مقصد: ${destination.ownerDisplayName} · ${destination.name}'),
            SelectableText(destination.accountNumber,
                textDirection: TextDirection.ltr),
            Text('مبلغ: ${formatGol(amount)}'),
            if (c.reviewDescription?.isNotEmpty == true)
              Text('توضیح: ${c.reviewDescription}'),
            const SizedBox(height: 8),
            const Text(
              'پس از تأیید، همین مبدأ، مقصد، مبلغ و شناسه درخواست ثابت می‌مانند.',
            ),
            const SizedBox(height: 16),
            FilledButton(
              key: const Key('transfer-confirm'),
              onPressed: () => unawaited(c.confirm()),
              child: const Text('تأیید و انتقال'),
            ),
            const SizedBox(height: 8),
            TextButton(
              key: const Key('transfer-cancel'),
              onPressed: c.cancelReview,
              child: const Text('انصراف'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _unknown(NajmBaharTransferController c) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('نتیجهٔ انتقال هنوز مشخص نیست',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            const Text(
              'برای جلوگیری از انتقال دوباره، ابتدا نتیجهٔ همان درخواست را بررسی کنید. مبدأ، مقصد و مبلغ تا تعیین نتیجه ثابت می‌مانند.',
            ),
            if (c.selectedSource != null) ...[
              const SizedBox(height: 8),
              Text('مبدأ: ${c.selectedSource!.accountNumber}'),
            ],
            if (c.destination != null)
              Text('مقصد: ${c.destination!.accountNumber}'),
            if (c.reviewAmountGol != null)
              Text('مبلغ: ${formatGol(c.reviewAmountGol!)}'),
            const SizedBox(height: 12),
            FilledButton.tonal(
              key: const Key('transfer-reconcile'),
              onPressed: () => unawaited(c.reconcile()),
              child: const Text('بررسی نتیجه'),
            ),
            const SizedBox(height: 8),
            OutlinedButton(
              key: const Key('transfer-retry-same-intent'),
              onPressed: () => unawaited(c.retrySameIntent()),
              child: const Text('تلاش دوباره با همان درخواست'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _confirmed(NajmBaharTransferController c) {
    final transaction = c.receipt!.transaction;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('انتقال با موفقیت ثبت شد',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            Text('مبلغ: ${formatGol(transaction.amountGol)}'),
            if (transaction.counterparty != null) ...[
              Text('مقصد: ${transaction.counterparty!.name}'),
              SelectableText(transaction.counterparty!.accountNumber,
                  textDirection: TextDirection.ltr),
            ],
            Text('شماره پیگیری: ${transaction.trackingNumber}',
                textDirection: TextDirection.ltr),
            const SizedBox(height: 12),
            OutlinedButton(
              key: const Key('transfer-new'),
              onPressed: () {
                _destination.clear();
                _amount.clear();
                _description.clear();
                unawaited(c.prepare());
              },
              child: const Text('انتقال جدید'),
            ),
          ],
        ),
      ),
    );
  }

  static String _normalizeAccount(String raw) =>
      raw.replaceAll(RegExp(r'\s+'), '').replaceAll('/', '-');

  static int? _parseBaharToGol(String raw) {
    final value = raw.trim();
    if (!RegExp(r'^\d+(?:\.\d{1,2})?$').hasMatch(value)) return null;
    final parts = value.split('.');
    final whole = BigInt.tryParse(parts[0]);
    if (whole == null) return null;
    final fractionText =
        parts.length == 1 ? '00' : parts[1].padRight(2, '0');
    final fraction = BigInt.tryParse(fractionText);
    if (fraction == null) return null;
    final total = whole * BigInt.from(100) + fraction;
    if (total <= BigInt.zero || total > BigInt.from(0x7fffffffffffffff)) {
      return null;
    }
    return total.toInt();
  }

  static String _failureText(ApiFailure failure) => switch (failure.code) {
        'not_found' => 'حساب مقصد یافت نشد یا دیگر فعال نیست.',
        'transfer_destination_internal' =>
          'برای حساب‌های خودتان باید از جریان انتقال داخلی استفاده شود.',
        'transfer_destination_changed' =>
          'اطلاعات مقصد تغییر کرده است؛ مقصد را دوباره بررسی کنید.',
        'transfer_terms_changed' =>
          'شرایط مبدأ یا موجودی قابل انتقال تغییر کرده است.',
        'insufficient_available_funds' =>
          'بهار فعال قابل انتقال برای این مبلغ کافی نیست.',
        'transfer_not_allowed' =>
          'این انتقال طبق سیاست فعلی نجم بهار مجاز نیست.',
        'unauthenticated' || 'session_changed' =>
          'برای ادامه دوباره وارد حساب شوید.',
        'bootstrap_unavailable' =>
          'اتصال امن فعلاً آماده نیست. پس از بازیابی دوباره تلاش کنید.',
        'network_error' => 'ارتباط با سرور کامل نشد.',
        _ => 'عملیات انتقال کامل نشد. دوباره بررسی کنید.',
      };
}
