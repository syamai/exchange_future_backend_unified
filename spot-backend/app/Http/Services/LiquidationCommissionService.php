<?php


namespace App\Http\Services;


use App\Consts;
use App\IdentifierHelper;
use App\Models\LiquidationCommissionDetail;
use App\Models\PartnerRequest;
use App\Models\UserRates;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LiquidationCommissionService
{
    private $activityHistoryService;
    private $exportExelService;
    private $identifierHelper;

    public function __construct(ActivityHistoryService $activityHistoryService, ExportExelService $exportExelService, IdentifierHelper $identifierHelper,)
    {
        $this->activityHistoryService = $activityHistoryService;
        $this->exportExelService = $exportExelService;
        $this->identifierHelper = $identifierHelper;
    }

    public function updateUserRate($rate, $id, $user): void
    {
        $oldRate = 0;
        $newRate = $rate;
        $userRate = UserRates::find($id);
        if ($userRate) {
            $oldRate = BigNumber::new($userRate->liquidation_rate);
            $userRate->liquidation_rate = $rate;
            $userRate->save();
        } else {
            UserRates::create([
                'id' => $id,
                'liquidation_rate' => $rate
            ]);
        }

        PartnerRequest::create([
            'user_id' => $id,
            'type' => Consts::PARTNER_REQUEST_ADMIN_CHANGE_LIQUIDATION_RATE,
            'detail' => Consts::PARTNER_REQUEST_DETAIL[Consts::PARTNER_REQUEST_ADMIN_CHANGE_LIQUIDATION_RATE],
            'old' => $oldRate,
            'new' => $newRate,
            'status' => Consts::PARTNER_REQUEST_APPROVED,
            'processed_by' => $user->id
        ]);

        $this->activityHistoryService->create([
            'page' => Consts::ACTIVITY_HISTORY_PAGE_PARTNER_ADMIN,
            'type' => Consts::ACTIVITY_HISTORY_TYPE_CHANGE_LIQUIDATION_COMMISSION_RATE,
            'actor_id' => $user->id,
            'target_id' => $id
        ]);
    }

    public function exportDetail($liquidationCommission, $request)
    {
        $searchKey = trim($request->search_key ?? "");

        $items = LiquidationCommissionDetail::userWithWhereHas($searchKey)
            ->where('liquidation_commission_id', $liquidationCommission->id)
            ->orderByDesc('id')
            ->get();

        $data = collect();
        foreach ($items as $item) {
            $tmp = [
                'date' => $liquidationCommission->date,
                'userAccount' => $item->user->email,
                'liqCommissionRate' => $liquidationCommission->rate,
                'liquidateionAmount' => $item->amount,
                'EstCommission' => BigNumber::round(BigNumber::new($item->amount)->mul($liquidationCommission->rate)->div(100), BigNumber::ROUND_MODE_HALF_UP, 2)
            ];

            if ($liquidationCommission->status == 'completed') {
                $tmp['settlementDate'] = Carbon::parse($liquidationCommission->complete_at)->format("Y-m-d H:i");
            }
            $data->push(collect($tmp));
        }


        $uniqueIdentifier = $this->identifierHelper->generateUniqueIdentifier();
        $export = $this->exportExelService;
        $ext = $request->ext ?? 'csv';

        return $export->export($request, "exportDetailLiq_{$uniqueIdentifier}.{$ext}", $ext, 6, $data);
    }
}