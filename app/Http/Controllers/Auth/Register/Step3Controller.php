<?php

namespace App\Http\Controllers\Auth\Register;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Alley;
use App\Models\Continent;
use App\Models\Country;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationStructureClaim;
use App\Models\ReferenceSettlement;
use App\Models\ReferenceSettlementResidenceClaim;
use App\Models\Neighborhood;
use App\Models\Province;
use App\Models\Region;
use App\Models\Street;
use App\Models\Village;
use App\Services\GroupService;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\LocationGovernance\LocationProposalPolicy;
use App\Services\LocationGovernance\LocationTreeResolver;
use App\Services\LocationGovernance\IranSettlementAnchorResolver;
use App\Services\LocationGovernance\ResidenceService;
use App\Services\ProfileCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Step3Controller extends Controller
{
    public function show()
    {
        if ((bool) config('location-governance.registration_enabled')) {
            $hasPrimaryResidence = auth()->user()?->locationRelationships()
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')
                ->exists();

            if ($hasPrimaryResidence) {
                return redirect()->route('home')->with('error', 'محل سکونت اصلی شما قبلا ثبت شده است.');
            }
        } else {
            $address = Address::where('user_id', auth()->user()->id)->first();
            if ($address) {
                return redirect()->route('home')->with('error', 'آدرس شما قبلا ثبت شده است.');
            }
        }

        $continents = Continent::where('status', 1)->get();
        $countries = Country::where('continent_id', 4)->where('status', 1)->get();
        $provinces = Province::where('country_id', 74)->get();

        return view('auth.register_step3', compact('continents', 'countries', 'provinces'));
    }

    public function process(
        Request $request,
        LocationTreeResolver $locationTreeResolver,
        ResidenceService $residenceService,
        LocationProposalPolicy $proposalPolicy,
        IranSettlementAnchorResolver $settlementAnchorResolver,
    ) {
        $user = auth()->user();
        if (! $user) {
            return redirect()->route('login')->withErrors('ابتدا وارد حساب خود شوید.');
        }

        if ((bool) config('location-governance.registration_enabled')) {
            $validated = $request->validate([
                'location_id' => ['nullable', 'integer', 'exists:locations,id'],
                'location_proposal_id' => ['nullable', 'integer', 'exists:location_proposals,id'],
                'reference_settlement_external_id' => ['nullable', 'string', 'regex:/^IR-1404-[1-9][0-9]*$/D'],
                'location_structure_claim_ids' => ['nullable', 'array'],
                'location_structure_claim_ids.*' => ['integer', 'distinct', 'exists:location_structure_claims,id'],
            ]);

            $locationId = $validated['location_id'] ?? null;
            $proposalId = $validated['location_proposal_id'] ?? null;
            $referenceSettlementExternalId = $validated['reference_settlement_external_id'] ?? null;
            $structuralClaims = LocationStructureClaim::query()
                ->whereIn('id', $validated['location_structure_claim_ids'] ?? [])
                ->get()
                ->all();

            $hasLocation = $locationId !== null;
            $hasProposal = $proposalId !== null;
            $hasReferenceSettlement = $referenceSettlementExternalId !== null;
            if ((! $hasLocation && ! $hasProposal && ! $hasReferenceSettlement)
                || ($hasLocation && ($hasProposal || $hasReferenceSettlement))) {
                throw ValidationException::withMessages([
                    'location_id' => 'لطفاً یک مسیر معتبر محل سکونت را انتخاب کنید.',
                ]);
            }

            if ($locationId !== null) {
                $location = Location::query()->findOrFail($locationId);

                if ($location->status !== 'active' || ! $locationTreeResolver->registrationEndpointAllowed($location, $structuralClaims)) {
                    throw ValidationException::withMessages([
                        'location_id' => 'لطفاً یک محل سکونت معتبر و قابل انتخاب را مشخص کنید.',
                    ]);
                }

                $residenceService->setInitialPrimaryResidence($user, $location, [
                    'source' => 'registration_step3',
                ], $structuralClaims);

                app(ProfileCompletionService::class)->maybeAward($user->fresh());

                return redirect()->route('home')->with('success', 'تبریک می‌گوییم! اطلاعات شما با موفقیت دریافت شد، ثبت‌نام شما تکمیل شد و در گروه‌های مربوط به خود عضو شدید. به EarthCoop خوش آمدید.');
            }

            if ($referenceSettlementExternalId !== null) {
                if (! (bool) config('iran_settlement_catalog.enabled', false)
                    || ! (bool) config('iran_settlement_catalog.claims_enabled', false)) {
                    throw ValidationException::withMessages([
                        'reference_settlement_external_id' => 'انتخاب آبادی مرجع در حال حاضر فعال نیست.',
                    ]);
                }
                [$settlementClaim, $settlementProposal, $settlementStructuralClaims] = DB::transaction(function () use (
                    $user,
                    $proposalId,
                    $referenceSettlementExternalId,
                    $settlementAnchorResolver,
                    $residenceService,
                    $structuralClaims,
                ): array {
                    $settlement = ReferenceSettlement::query()
                        ->where('source', 'IranCountryDivisions/geo_1404')
                        ->where('dataset_version', 'v2')
                        ->where('external_id', $referenceSettlementExternalId)
                        ->lockForUpdate()->firstOrFail();
                    $claimable = (
                        in_array($settlement->classification, ['unverified_settlement', 'needs_review'], true)
                        && $settlement->residential_eligibility === 'unverified'
                    ) || (
                        $settlement->classification === 'verified_residential_village'
                        && $settlement->residential_eligibility === 'verified'
                    );
                    if (! $claimable || $settlement->governance_authorized || $settlement->operational_promotion_allowed) {
                        throw ValidationException::withMessages([
                            'reference_settlement_external_id' => 'این آبادی در وضعیت قابل انتخاب برای ثبت سکونت نیست.',
                        ]);
                    }

                    $referenceStructuralClaims = collect($structuralClaims)
                        ->filter(fn (LocationStructureClaim $claim): bool =>
                            (int) $claim->reference_settlement_id === (int) $settlement->id
                            && $claim->claim_type === 'no_neighborhood'
                            && in_array($claim->status, ['pending', 'ready_for_review', 'needs_evidence', 'approved'], true)
                        )->values();
                    if ($referenceStructuralClaims->count() !== count($structuralClaims)) {
                        throw ValidationException::withMessages([
                            'location_structure_claim_ids' => 'اعلام ساختاری انتخاب‌شده متعلق به همین آبادی مرجع نیست.',
                        ]);
                    }
                    $hasNoNeighborhood = $referenceStructuralClaims->contains('claim_type', 'no_neighborhood');

                    $anchor = $settlementAnchorResolver->resolve($settlement);
                    $claim = ReferenceSettlementResidenceClaim::query()->firstOrCreate(
                        ['reference_settlement_id' => $settlement->id, 'user_id' => $user->id],
                        [
                            'status' => $settlement->residential_eligibility === 'verified' ? 'residential_evidence_verified' : 'pending',
                            'submitted_at' => now(),
                        ],
                    );

                    $proposal = null;
                    if ($proposalId !== null) {
                        $proposal = LocationProposal::query()->with('type')->whereKey($proposalId)->lockForUpdate()->first();
                        if ($proposal === null
                            || ! in_array($proposal->status, [LocationProposalStatus::Pending, LocationProposalStatus::ReadyForReview, LocationProposalStatus::NeedsEvidence], true)
                            || (int) $proposal->parent_reference_settlement_id !== (int) $settlement->id
                            || $proposal->type?->key !== 'neighborhood'
                            || $hasNoNeighborhood) {
                            throw ValidationException::withMessages([
                                'location_proposal_id' => 'محلهٔ انتخاب‌شده متعلق به همین آبادی مرجع نیست یا با اعلام بی‌محله بودن تعارض دارد.',
                            ]);
                        }
                    }

                    if ($proposal === null && ! $hasNoNeighborhood) {
                        throw ValidationException::withMessages([
                            'reference_settlement_external_id' => 'برای تکمیل ثبت‌نام، محله را انتخاب کنید یا بی‌محله بودن آبادی را اعلام کنید.',
                        ]);
                    }

                    $residenceService->setInitialPrimaryResidence($user, $anchor, [
                        'source' => 'registration_step3_reference_settlement_anchor',
                        'reference_settlement_external_id' => $settlement->external_id,
                    ]);
                    if ($proposal !== null) {
                        $residenceService->setPendingReferenceSettlementProposalIntent(
                            $user, $claim, $proposal, $anchor,
                            [
                                'source' => 'registration_step3_reference_settlement_neighborhood',
                                'structural_claim_ids' => $referenceStructuralClaims->pluck('id')->map(fn ($id) => (int) $id)->all(),
                            ],
                        );
                    } else {
                        $residenceService->setPendingReferenceSettlementIntent(
                            $user, $claim, $anchor,
                            [
                                'source' => 'registration_step3_reference_settlement',
                                'structural_claim_ids' => $referenceStructuralClaims->pluck('id')->map(fn ($id) => (int) $id)->all(),
                            ],
                        );
                    }
                    return [$claim, $proposal, $referenceStructuralClaims];
                });

                foreach ($settlementStructuralClaims as $structuralClaim) {
                    app(\App\Services\LocationGovernance\LocationStructureClaimService::class)
                        ->recordCommittedSupport($structuralClaim, $user, [
                            'source' => 'registration_step3_reference_settlement',
                            'reference_settlement_external_id' => $referenceSettlementExternalId,
                        ]);
                }

                if ((bool) config('location-governance.groups_enabled', false)) {
                    app(CanonicalGroupMembershipReconciler::class)->reconcile($user->fresh());
                    $pendingGroups = app(\App\Services\Groups\PendingLocationGroupRequestService::class);
                    if ($settlementProposal !== null) {
                        $pendingGroups->syncForReferenceSettlementProposal($user->fresh(), $settlementClaim, $settlementProposal);
                    } else {
                        $pendingGroups->syncForReferenceSettlementClaim($user->fresh(), $settlementClaim);
                    }
                }
                app(ProfileCompletionService::class)->maybeAward($user->fresh());

                return redirect()->route('home')->with(
                    'success',
                    $settlementProposal !== null
                        ? 'ثبت‌نام شما تکمیل شد. آبادی و محلهٔ انتخابی تا بررسی انسانی در وضعیت pending می‌مانند و هیچ حوزهٔ حکمرانی رسمی خودکار ایجاد نشده است.'
                        : 'ثبت‌نام شما تکمیل شد. آبادی انتخابی شما به‌عنوان محل دقیق در انتظار بررسی/تطبیق باقی می‌ماند و حوزهٔ رسمی فعلاً بر اساس نزدیک‌ترین والد canonical تأییدشده محاسبه می‌شود.'
                );
            }

            DB::transaction(function () use (
                $user,
                $proposalId,
                $locationTreeResolver,
                $residenceService,
                $proposalPolicy,
                $structuralClaims,
            ): void {
                $proposal = LocationProposal::query()
                    ->with(['parentLocation', 'parentProposal', 'type'])
                    ->whereKey($proposalId)
                    ->lockForUpdate()
                    ->first();

                if ($proposal === null || ! in_array($proposal->status, [
                    LocationProposalStatus::Pending,
                    LocationProposalStatus::ReadyForReview,
                    LocationProposalStatus::NeedsEvidence,
                ], true)) {
                    throw ValidationException::withMessages([
                        'location_proposal_id' => 'این پیشنهاد مکان دیگر در وضعیت قابل انتخاب نیست.',
                    ]);
                }

                $anchor = $proposal->nearestCanonicalParent();
                $type = $proposal->type;
                $parentProposal = $proposal->parentProposal;
                $canonicalStructuralClaims = array_values(array_filter(
                    $structuralClaims,
                    fn (LocationStructureClaim $claim): bool => $claim->location_id !== null,
                ));
                $proposalStructuralClaims = array_values(array_filter(
                    $structuralClaims,
                    fn (LocationStructureClaim $claim): bool => $claim->location_proposal_id !== null,
                ));
                $proposalPathAllowed = $proposal->parent_location_id !== null
                    ? ($anchor !== null && $type !== null && $proposalPolicy->allowsForResidence($anchor, $type, $canonicalStructuralClaims))
                    : ($parentProposal !== null && $type !== null && $proposalPolicy->allowsProposalParentForResidence($parentProposal, $type, $structuralClaims));
                $pendingEndpointAllowed = $locationTreeResolver->proposalRegistrationEndpointAllowed(
                    $proposal,
                    $proposalStructuralClaims,
                );

                if (
                    $anchor === null
                    || $type === null
                    || $anchor->status !== 'active'
                    || ! $proposalPathAllowed
                    || ! $pendingEndpointAllowed
                    || in_array($type->key, ['street', 'alley', 'complex', 'building'], true)
                ) {
                    throw ValidationException::withMessages([
                        'location_proposal_id' => 'پیشنهاد مکان انتخاب‌شده با مسیر معتبر محل سکونت سازگار نیست.',
                    ]);
                }

                $residenceService->setInitialPrimaryResidence($user, $anchor, [
                    'source' => 'registration_step3_pending_anchor',
                    'location_proposal_id' => $proposal->id,
                ], $canonicalStructuralClaims);

                $residenceService->setPendingResidenceIntent($user, $proposal, [
                    'source' => 'registration_step3',
                ], $proposalStructuralClaims, $canonicalStructuralClaims);
            });

            if ((bool) config('location-governance.groups_enabled', false)) {
                app(CanonicalGroupMembershipReconciler::class)->reconcile($user->fresh());
                app(\App\Services\Groups\PendingLocationGroupRequestService::class)
                    ->syncForPendingResidence($user->fresh(), LocationProposal::query()->findOrFail($proposalId));
            }
            app(ProfileCompletionService::class)->maybeAward($user->fresh());

            return redirect()->route('home')->with(
                'success',
                'ثبت نام شما تکمیل شد. محل دقیق انتخابی شما در انتظار بررسی است و تا زمان تأیید، حوزه رسمی شما بر اساس نزدیک‌ترین مکان تأییدشده محاسبه می‌شود.'
            );
        }

        $validated = $request->validate([
            'continent_id'     => 'required|exists:continents,id',
            'country_id'       => 'required|exists:countries,id',
            'province_id'      => 'required|exists:provinces,id',
            'county_id'        => 'required|exists:counties,id',
            'section_id'       => 'required|exists:districts,id',
            'city_id'          => 'required',
            'region_id'        => 'required',
            'neighborhood_id'  => 'required|exists:neighborhoods,id',
            'street_id'        => 'nullable|exists:streets,id',
            'alley_id'         => 'nullable|exists:alleies,id',
        ]);

        if (! method_exists($user, 'locations')) {
            return redirect()->route('home')->withErrors('متد locations در مدل User تعریف نشده است.');
        }

        $addressData = [
            'status' => 1,
            'user_id' => $user->id,
            'continent_id' => $validated['continent_id'],
            'country_id' => $validated['country_id'],
            'province_id' => $validated['province_id'],
            'county_id' => $validated['county_id'],
            'section_id' => $validated['section_id'],
            'neighborhood_id' => $validated['neighborhood_id'],
            'street_id' => $validated['street_id'] ?? null,
            'alley_id' => $validated['alley_id'] ?? null,
        ];

        if (strpos($validated['city_id'], 'rural_') === 0) {
            $ruralId = (int) str_replace('rural_', '', $validated['city_id']);
            $addressData['rural_id'] = $ruralId;
            $addressData['village_id'] = $validated['region_id'];
            $addressData['city_id'] = null;
            $addressData['region_id'] = null;
        } elseif (strpos($validated['city_id'], 'city_') === 0) {
            $cityId = (int) str_replace('city_', '', $validated['city_id']);
            $addressData['city_id'] = $cityId;
            $addressData['region_id'] = $validated['region_id'];
            $addressData['rural_id'] = null;
            $addressData['village_id'] = null;
        } else {
            return back()->withErrors(['city_id' => 'فرمت شهر/دهستان نامعتبر است.']);
        }

        if (isset($addressData['region_id']) && $addressData['region_id']) {
            $region = Region::find($addressData['region_id']);
        } elseif (isset($addressData['village_id']) && $addressData['village_id']) {
            $region = Village::find($addressData['village_id']);
        } else {
            return back()->withErrors(['region_id' => 'منطقه یا روستا انتخاب نشده است.']);
        }

        $neighborhood = Neighborhood::find($addressData['neighborhood_id']);
        $street = $addressData['street_id'] ? Street::find($addressData['street_id']) : null;
        $alley = $addressData['alley_id'] ? Alley::find($addressData['alley_id']) : null;

        if ($region && $region->status == 0) {
            $addressData['status'] = 0;
        } elseif ($neighborhood && $neighborhood->status == 0) {
            $addressData['status'] = 0;
        } elseif ($street && $street->status == 0) {
            $addressData['status'] = 0;
        } elseif ($alley && $alley->status == 0) {
            $addressData['status'] = 0;
        }

        Address::create($addressData);

        $user->refresh();
        $user->load([
            'address.continent',
            'address.country',
            'address.province',
            'address.county',
            'address.section',
            'address.city',
            'address.rural',
            'address.region',
            'address.village',
            'address.neighborhood',
            'address.street',
            'address.alley',
            'specialties',
            'experiences',
        ]);

        $groupService = new GroupService();
        $groupService->generateGroupsForUser($user);

        app(ProfileCompletionService::class)->maybeAward($user);

        return redirect()->route('home')->with('success', 'تبریک میگوییم، داده های شما دریافت و ثبت نام شما تکمیل شد و شما در گروه های مربوطه عضو شدید.\nاکنون به داشبورد وارد میشوید. برای ایجاد حساب مالی نجم بهار، روی لینک "حساب مالی نجم بهار" کلیک کنید.\n\nبا تشکر تیم توسعه EarthCoop');
    }
}
