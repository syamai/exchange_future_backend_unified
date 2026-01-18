<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MyOrderTransactionResource extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {

        $currentPage = $this->currentPage();
        $perPage = $this->perPage();

        return [
            'status' => true,
            'total' => $this->total(),
            "last_page" => $this->lastPage(),
            'per_page' => $perPage,
            'current_page' => $currentPage,
            "next_page_url" => $this->nextPageUrl(),
            "prev_page_url" => $this->previousPageUrl(),
            "from" => $perPage * ($currentPage - 1) + 1,
            "to" => $perPage * ($currentPage - 1) + $this->count(),

            'data' => $this->getData(),
        ];
    }

    private function getData()
    {
        $data = [];
        foreach ($this->collection as $item) {
            $data[] = [
                'id' => $item->id,
                'volume' => $item->quantity,
                'side' => $item->transaction_type,
                'feeCoin' => $this->getFeeCoin($item),
                'price' => $item->price,
                'fee' => $this->getFee($item),
                'ctime' => $item->created_at,
                'deal_price' => $item->price,
                'type' => 'Purchase',
                'bid_id' => $item->buy_order_id,
                'ask_id' => $item->sell_order_id,
                'bid_user_id' => $item->buyer_id,
                'ask_user_id' => $item->seller_id
            ];
        }

        return $data;
    }

    private function getFee($item)
    {
        if ($item->id === $item->buyer_id) {
            return $item->coin;
        }

        return $item->currency;
    }

    private function getFeeCoin($item)
    {
        if ($item->id === $item->buyer_id) {
            return $item->buy_fee;
        }

        return $item->sell_fee;
    }
}
