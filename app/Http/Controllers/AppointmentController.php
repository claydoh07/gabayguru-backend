<?php

namespace App\Http\Controllers;

use App\Constants;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Classes;
use App\Models\User;
use App\Models\UserTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $appointmentsResource = AppointmentResource::collection(Appointment::with(['mentor','student','class'])->get());
        return json_encode( $appointmentsResource, 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAppointmentRequest $request)
    {
        $student = User::find($request->student_id);
        $mentor = User::find($request->mentor_id);
        if($student->wallet < $mentor->rate) {
            return response()->json("Insufficient balance, please reload your e-wallet", 400);
        }
        $scheduleExists = $mentor->mentor_appointments->where('date', Carbon::parse($request->date))->first();
        if($scheduleExists){
            return response()->json("Schedule already taken", 400);
        }
        $matched = 0;
        foreach ($mentor->schedules as $schedule) {
            $requestDate = Carbon::parse($request->date);
            $dayName = $requestDate->dayName; 
            if ($dayName == $schedule->day){
                $time = $requestDate->toTimeString();
                $parseTime = Carbon::parse($time);
                $from = Carbon::parse($schedule->from);
                $to = Carbon::parse($schedule->to);
                
                $hit = Carbon::createFromTimeString($parseTime);
                $start = Carbon::createFromTimeString($from);
                $end = Carbon::createFromTimeString($to);

                if ($hit->between($start, $end)) {
                    $matched++;
                }
            }
        }
        if ($matched == 0) {
            return json_encode ("Invalid Schedule");
        }

        // add validation for available schedule
        DB::beginTransaction();
        try {
            $appointment = Appointment::create(array_merge($request->validated(), [
                'status' => "PENDING"
            ]));
            $appointmentRelationship = Appointment::with(['mentor','student'])->find($appointment->id);
            $appointmentResource = new AppointmentResource($appointmentRelationship);
            DB::commit();
            return json_encode( $appointmentResource, 200);
        } catch (\Exception $e) {
            DB::rollback();
            return json_encode( $e, 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        DB::beginTransaction();

        if($appointment->status == Constants::APPOINTMENT_PENDING && $request->status == Constants::APPOINTMENT_APPROVED) {
            // Create Class
            Classes::insert([
                    'appointment_id' => $appointment->id,
                    'name' => $appointment->mentor->first_name . " and " . $appointment->student->first_name . " Class", 
                    'class_id' => "PLACEHOLDER",
                    'start_time' => date('Y-m-d H:i:s'),
                    'end_time' => date('Y-m-d H:i:s'),
                    'end_time' => date('Y-m-d H:i:s'),
                    'duration' => "1 Hour",
                    'status' => Constants::APPOINTMENT_APPROVED,
                    'created_at' =>  date('Y-m-d H:i:s'),
                    'updated_at' =>  date('Y-m-d H:i:s')
            ]);

        } else if($appointment->status == Constants::APPOINTMENT_APPROVED && $request->status == Constants::APPOINTMENT_DONE) {
            // Continue Payment
            $student = User::find($appointment->student_id);
            $mentor = User::find($appointment->mentor_id);
            $old_student_balance = $student->wallet;
            $new_student_balance = -$appointment->amount + $student->wallet;
            $old_mentor_balance = $mentor->wallet;
            $new_mentor_balance = $appointment->amount + $mentor->wallet;

            UserTransaction::insert([
                [
                    'user_id' => $appointment->student_id,
                    'amount' => -$appointment->amount,
                    'description' => Constants::APPOINTMENT_DONE_STUDENT,
                    'old_balance' => $old_student_balance,
                    'new_balance' => $new_student_balance,
                    'created_at' =>  date('Y-m-d H:i:s'),
                    'updated_at' =>  date('Y-m-d H:i:s')
                ],
                [
                    'user_id' => $appointment->mentor_id,
                    'amount' => $appointment->amount,
                    'description' => Constants::APPOINTMENT_DONE_MENTOR,
                    'old_balance' => $old_mentor_balance,
                    'new_balance' => $new_mentor_balance,
                    'created_at' =>  date('Y-m-d H:i:s'),
                    'updated_at' =>  date('Y-m-d H:i:s')
                ]
            ]);
            $student->wallet = $new_student_balance;
            $student->save();
            $mentor->wallet = $new_mentor_balance;
            $mentor->save();

        } else if($appointment->status == Constants::APPOINTMENT_APPROVED && $request->status == Constants::APPOINTMENT_FAILED) {
            // Void Payment
        } else {
            return json_encode( "Something went wrong. If issue persists, contact administrator", 400);
        }
        $appointment->status = $request->status;
        $appointment->save();
        DB::commit();
        $appointmentRelationship = Appointment::with(['mentor','student','class'])->find($appointment->id);
        $appointmentResource = new AppointmentResource($appointmentRelationship);
        return json_encode( $appointmentResource, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
