<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->role == 2 ? "mentor_id" : "student_id";
        $url = $this->profile_picture;
        $local = array("http://127.0.0.1:8000");
        $live   = array("https://e250-112-209-252-113.ngrok-free.app");
        $profile_picture = str_replace($local, $live, $url);
        return [
            'id'                    => $this->id,
            'first_name'            => $this->first_name,
            'last_name'             => $this->last_name,
            'username'              => $this->username,
            'email'                 => $this->email,
            'contact_number'        => $this->contact_number,
            'date_of_birth'         => $this->date_of_birth,
            'profile_picture'       => $profile_picture,
            'role'                  => $this->role,
            'status'                => $this->status,
            'rate'                  => $this->rate,
            'wallet'                => $this->wallet,
            'transactions'          => UserTransactionResource::collection($this->whenLoaded('transactions')),
            'schedules'             => ScheduleResource::collection($this->whenLoaded('schedules')),
            'student_appointments'  => AppointmentResource::collection($this->whenLoaded('student_appointments')),
            'mentor_appointments'   => AppointmentResource::collection($this->whenLoaded('mentor_appointments')),
            'latest_appointment'    => Appointment::where([$type => $this->id, 'status' => "APPROVED"])->where('date', '>=', 'NOW()')->orderBy('date', 'asc')->first(),
            'support_tickets'       => TicketResource::collection($this->whenLoaded('support_tickets')),
            'student_tickets'       => TicketResource::collection($this->whenLoaded('student_tickets')),
            'penalties'             => PenaltyResource::collection($this->whenLoaded('penalties')),
            'classes'               => $this->classes,
            'reviews'               => ReviewResource::collection($this->reviews),
            'user_payment_details'  => new UserPaymentDetailsResource($this->whenLoaded('payment_details')),
            'user_bio'              => new UserBioResource($this->whenLoaded('user_bio')),
            'created_at'            => (string) $this->created_at,
            'created_at_text'       => Carbon::parse($this->created_at)->format('M d, Y'),
            'updated_at'            => (string) $this->created_at,
            'updated_at_text'       => Carbon::parse($this->updated_at)->format('M d, Y')
        ];
    }
}
