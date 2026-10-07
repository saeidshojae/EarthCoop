import 'dart:async';

import 'package:flutter/material.dart';

import 'najm_bahar_dto.dart';
import 'najm_bahar_membership_payment_controller.dart';
import 'najm_bahar_membership_source.dart';
import 'najm_bahar_policy_dto.dart';

class NajmBaharMembershipPaymentSection extends StatefulWidget {
  const NajmBaharMembershipPaymentSection({
    super.key,
    required this.controller,
  });

  final MembershipPaymentController controller;

  @override
  State<NajmBaharMembershipPaymentSection> createState() =>
      _NajmBaharMembershipPaymentSectionState();
}

class _NajmBaharMembershipPaymentSectionState
    extends State<NajmBaharMembershipPaymentSection> {
  @override
  void initState() {
    super.initState();
    _ensureDefaultSelection();
    widget.controller.addListener(_changed);
  }

  @override
  void didUpdateWidget(covariant NajmBaharMembershipPaymentSection oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller.removeListener(_changed);
      _ensureDefaultSelection();
      widget.controller.addListener(_changed);
    }
  }

  @override
  void dispose() {
    widget.controller.removeListener(_changed);
    super.dispose();
  }

  void _changed() {
    if (!mounted) {
      return;
    }
    _ensureDefaultSelection();
    setState(() {});
  }

  void _ensureDefaultSelection() {
    final controller = widget.controller;
    final terms = controller.terms;
    if (controller.state != MembershipPaymentState.ready ||
        terms == null ||
        terms.paymentContractVersion != 1 ||
        controller.selectedSource != null) {
      return;
    }
    final dim = terms.paymentSources
        .where((source) => source.kind == 'main' && source.canPayDim)
        .toList(growable: false);
    if (dim.isNotEmpty) {
      controller.selectSource(dim.first, 'dim');
      return;
    }
    final active =
        terms.paymentSources.where((source) => source.canPayActive).toList();
    if (active.isNotEmpty) {
      controller.selectSource(active.first, 'active');
    }
  }

  @override
  Widget build(BuildContext context) {
    final controller = widget.controller;
    final terms = controller.terms;

    if (controller.state == MembershipPaymentState.loading && terms == null) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: LinearProgressIndicator(),
        ),
      );
    }

    if (controller.state == MembershipPaymentState.confirmedPaid ||
        controller.obligationPaid ||
        terms?.hasPaid == true) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Text('حق عضویت این دوره پرداخت شده است.'),
        ),
      );
    }

    if (controller.state == MembershipPaymentState.outcomeUnknown) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'نتیجهٔ پرداخت هنوز مشخص نیست',
                style: TextStyle(fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              const Text(
                'برای جلوگیری از پرداخت دوباره، ابتدا نتیجه را بررسی کنید یا همان درخواست قبلی را با همان شناسه دوباره ارسال کنید.',
              ),
              const SizedBox(height: 12),
              FilledButton.tonal(
                key: const Key('membership-reconcile'),
                onPressed: () => unawaited(controller.reconcile()),
                child: const Text('بررسی نتیجه'),
              ),
              const SizedBox(height: 8),
              OutlinedButton(
                key: const Key('membership-retry-same-intent'),
                onPressed: () => unawaited(controller.retrySameIntent()),
                child: const Text('تلاش دوباره با همان درخواست'),
              ),
            ],
          ),
        ),
      );
    }

    if (terms == null) {
      return const SizedBox.shrink();
    }

    if (terms.paymentContractVersion != 1 || terms.paymentSources.isEmpty) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Text(
            'حق عضویت دورهٔ ${terms.paymentYear}: ${formatGol(terms.feeGol)}. پرداخت نیتیو در این نسخهٔ سرور فعال نیست.',
          ),
        ),
      );
    }

    if (controller.state == MembershipPaymentState.confirming) {
      return _confirmation(controller);
    }

    if (controller.state == MembershipPaymentState.submitting) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              LinearProgressIndicator(),
              SizedBox(height: 12),
              Text('در حال ثبت همان درخواست پرداخت…'),
            ],
          ),
        ),
      );
    }

    if (controller.state == MembershipPaymentState.definiteRejected) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                  'پرداخت انجام نشد. اطلاعات پرداخت را دوباره دریافت کنید.'),
              const SizedBox(height: 8),
              OutlinedButton(
                onPressed: () => unawaited(controller.prepare()),
                child: const Text('دریافت دوباره شرایط پرداخت'),
              ),
            ],
          ),
        ),
      );
    }

    return _selection(controller, terms);
  }

  Widget _selection(
    MembershipPaymentController controller,
    NajmBaharMembershipFee terms,
  ) {
    final sources = List<MembershipSource>.from(terms.paymentSources);
    final dimSources = sources
        .where((source) => source.kind == 'main' && source.canPayDim)
        .toList(growable: false);
    final activeSources =
        sources.where((source) => source.canPayActive).toList(growable: false);

    final selectedBucket =
        controller.selectedBucket ?? (dimSources.isNotEmpty ? 'dim' : 'active');
    final eligible = selectedBucket == 'dim' ? dimSources : activeSources;
    var selected = controller.selectedSource;
    if (selected == null || !eligible.contains(selected)) {
      selected = eligible.isEmpty ? null : eligible.first;
    }

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'پرداخت حق عضویت',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Text('دوره: ${terms.paymentYear}'),
            Text('مبلغ: ${formatGol(terms.feeGol)}'),
            Text(
              'سهم عملیات و حقوق: ${formatGol(terms.operationsGol)}'
              ' · بیمه مرکزی: ${formatGol(terms.insuranceGol)}'
              ' · صندوق امحای پول: ${formatGol(terms.destructionGol)}',
            ),
            const SizedBox(height: 12),
            SegmentedButton<String>(
              segments: [
                ButtonSegment<String>(
                  value: 'dim',
                  enabled: dimSources.isNotEmpty,
                  label: const Text('بهار کمرنگ'),
                ),
                ButtonSegment<String>(
                  value: 'active',
                  enabled: activeSources.isNotEmpty,
                  label: const Text('بهار فعال'),
                ),
              ],
              selected: {selectedBucket},
              onSelectionChanged: (values) {
                final bucket = values.first;
                final options = bucket == 'dim' ? dimSources : activeSources;
                if (options.isEmpty) {
                  return;
                }
                controller.selectSource(options.first, bucket);
              },
            ),
            const SizedBox(height: 12),
            if (eligible.isNotEmpty)
              DropdownButtonFormField<MembershipSource>(
                key: ValueKey<String>('membership-account-$selectedBucket'),
                initialValue: selected,
                isExpanded: true,
                decoration: const InputDecoration(
                  labelText: 'حساب پرداخت',
                  border: OutlineInputBorder(),
                ),
                items: [
                  for (final source in eligible)
                    DropdownMenuItem<MembershipSource>(
                      value: source,
                      child: Text(
                        '${source.name} · ${source.accountNumber}',
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                ],
                onChanged: (source) {
                  if (source != null) {
                    controller.selectSource(source, selectedBucket);
                  }
                },
              ),
            if (eligible.isEmpty) ...[
              const SizedBox(height: 8),
              const Text('در این منبع، موجودی قابل پرداخت کافی وجود ندارد.'),
            ],
            const SizedBox(height: 12),
            FilledButton(
              key: const Key('membership-review'),
              onPressed: selected == null
                  ? null
                  : () {
                      controller.selectSource(selected!, selectedBucket);
                      controller.beginConfirmation();
                    },
              child: const Text('بررسی و تأیید پرداخت'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _confirmation(MembershipPaymentController controller) {
    final terms = controller.terms!;
    final source = controller.selectedSource!;
    final bucket = controller.selectedBucket!;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'تأیید پرداخت حق عضویت',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Text('دوره: ${terms.paymentYear}'),
            Text('مبلغ: ${formatGol(terms.feeGol)}'),
            Text('منبع: ${bucket == 'dim' ? 'بهار کمرنگ' : 'بهار فعال'}'),
            Text('حساب: ${source.name} · ${source.accountNumber}'),
            Text(
              'تقسیم مبلغ: ${formatGol(terms.operationsGol)} عملیات و حقوق، '
              '${formatGol(terms.insuranceGol)} بیمه مرکزی، '
              '${formatGol(terms.destructionGol)} امحای پول.',
            ),
            if (bucket == 'dim') ...[
              const SizedBox(height: 8),
              const Text(
                'بهار کمرنگ لازم برای این تعهد در همان عملیات مجاز فعال و پرداخت می‌شود.',
              ),
            ],
            const SizedBox(height: 16),
            FilledButton(
              key: const Key('membership-pay'),
              onPressed: () => unawaited(controller.confirm()),
              child: const Text('تأیید و پرداخت'),
            ),
            const SizedBox(height: 8),
            TextButton(
              key: const Key('membership-cancel'),
              onPressed: () => unawaited(controller.prepare()),
              child: const Text('انصراف'),
            ),
          ],
        ),
      ),
    );
  }
}
