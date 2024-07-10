<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $url = $this->screenshot;
        $local = array("http://127.0.0.1:8000");
        $live   = array("https://e250-112-209-252-113.ngrok-free.app");
        $screenshot = str_replace($local, $live, $url);
        return [
            'id'                => $this->id,
            'user'              => new UserResource($this->whenLoaded('user')),
            'amount'            => $this->amount,
            'service_charge'    => $this->service_charge,
            'description'       => $this->description,
            'old_balance'       => $this->old_balance,
            'new_balance'       => $this->new_balance,
            'reference_number'  => $this->reference_number,
            'screenshot'        => $screenshot,
            'sender_name'       => $this->sender_name,
            'account_name'      => $this->account_name,
            'account_number'    => $this->account_number,
            'status'            => $this->status == 0 ? ($this->processed == 0 ? "Pending" : "Declined") : "Accepted",
            'processed'         => $this->processed == 0 ? "False" : "True",
            'created_at'        => (string) $this->created_at,
            'created_at_text'   => Carbon::parse($this->created_at)->format('M d, Y'),
            'updated_at'        => (string) $this->created_at,
            'updated_at_text'   => Carbon::parse($this->updated_at)->format('M d, Y')
        ];
    }
}
