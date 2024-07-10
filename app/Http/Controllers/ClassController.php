<?php

namespace App\Http\Controllers;

use App\Constants;
use App\Http\Requests\ClassRequest;
use App\Http\Requests\StoreClassRequest;
use App\Http\Requests\UpdateClassRequest;
use App\Http\Resources\ClassResource;
use App\Models\Appointment;
use App\Models\Classes;
use App\Models\Schedule;
use App\Models\User;
use App\Models\UserTransaction;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Google\Client as Google_Client;
use Google\Service\Calendar as Google_Service_Calendar;
use Google\Service\Calendar\Event as Google_Service_Calendar_Event;
use Google\Service\Oauth2;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;

class ClassController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $classResource = ClassResource::collection(Classes::with(['appointment'])->get());
        return json_encode($classResource, 200);
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
    public function store(StoreClassRequest $request)
    {
        DB::beginTransaction();
        try {
            $class = Classes::create($request->validated());
            $classRelationship = Classes::with(['appointment'])->find($class->id);
            $classResource = new ClassResource($classRelationship);
            DB::commit();
            return json_encode($classResource, 200);
        } catch (\Exception $e) {
            DB::rollback();
            return json_encode($e, 400);
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
    public function update(UpdateClassRequest $request, Classes $class)
    {
        $class->fill($request->validated());
        $class->save();

        $classRelationship = Classes::with(['appointment'])->find($class->id);

        $classResource = new ClassResource($classRelationship);
        return json_encode($classResource, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function getGoogleCode()
    {

        $client_id = '1001131123526-u0ldfuqhk22t84t5o6ro2gnlk6lifhbh.apps.googleusercontent.com';
        $client_secret = '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';
        $authorization_code = $_GET['code'];
        $redirect_uri = 'https://e250-112-209-252-113.ngrok-free.app/api/v1/google-callback'; // Must match the redirect URI configured in your Google Cloud Console

        $url = 'https://oauth2.googleapis.com/token';

        $client = new Client();
        $response = $client->request('POST', $url, [
            'form_params' => [
                'code' => $authorization_code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code',
            ],
        ]);
        $body = $response->getBody();
        $data = json_decode($body, true);

        if (isset($data['access_token'])) {
            // Access token obtained successfully
            $access_token = $data['access_token'];
            $client = new Google_Client();
            $client->setClientId($client_id);
            $client->setClientSecret($client_secret);
            $client->setRedirectUri($redirect_uri);
            $client->addScope('https://www.googleapis.com/auth/calendar'); // Scope for Google Calendar API

            // Set access token
            $client->setAccessToken($access_token);

            // Create a Google Calendar service object
            $service = new Google_Service_Calendar($client);

            // Define event details
            $event = new Google_Service_Calendar_Event([
                'summary' => 'Meeting Title',
                'description' => 'Meeting description',
                'start' => [
                    'dateTime' => '2024-07-15T09:00:00-07:00', // Adjust start time as necessary
                    'timeZone' => 'America/Los_Angeles',
                ],
                'end' => [
                    'dateTime' => '2024-07-15T10:00:00-07:00', // Adjust end time as necessary
                    'timeZone' => 'America/Los_Angeles',
                ],
                'conferenceData' => [
                    'createRequest' => [
                        'requestId' => uniqid(),
                        'conferenceSolutionKey' => [
                            'type' => 'hangoutsMeet'
                        ]
                    ]
                ]
            ]);

            // Insert the event
            $calendarId = 'primary'; // Use 'primary' for the primary calendar of the authenticated user
            $event = $service->events->insert($calendarId, $event, ['conferenceDataVersion' => 1]);

            // Print the join URL for the Google Meet
            $meetLink = $event->getHangoutLink();;
            $appointment = Appointment::find(Cache::get('last_appointment'));
            $student = User::find($appointment->student_id);
            $old_student_balance = $student->wallet;
            $new_student_balance = -$appointment->amount + $student->wallet;

            UserTransaction::insert([
                [
                    'user_id' => $appointment->student_id,
                    'amount' => -$appointment->amount,
                    'description' => Constants::APPOINTMENT_APPROVED_STUDENT,
                    'old_balance' => $old_student_balance,
                    'new_balance' => $new_student_balance,
                    'reference_number' => 'AUTO_DEDUCT_FROM_SYSTEM',
                    'screenshot' => null,
                    'sender_name' => null,
                    'account_name' => null,
                    'account_number' => null,
                    'status' => 1,
                    'processed' => 1,
                    'created_at' =>  date('Y-m-d H:i:s'),
                    'updated_at' =>  date('Y-m-d H:i:s')
                ],
            ]);

            $student->wallet = $new_student_balance;
            $student->save();
            Classes::insert([
                'appointment_id' => $appointment->id,
                'name' => $appointment->mentor->first_name . " and " . $appointment->student->first_name . " Class",
                'class_id' => $meetLink,
                'start_time' => date('Y-m-d H:i:s'),
                'end_time' => date('Y-m-d H:i:s'),
                'end_time' => date('Y-m-d H:i:s'),
                'duration' => "1 Hour",
                'status' => Constants::APPOINTMENT_APPROVED,
                'created_at' =>  date('Y-m-d H:i:s'),
                'updated_at' =>  date('Y-m-d H:i:s')
            ]);

            $appointment->status = Constants::APPOINTMENT_APPROVED;
            $appointment->save();
            return Redirect::to("https://gabayguru-fe.vercel.app/session-request");
        } else {
            // Error handling if token retrieval fails

            return json_encode("Error: Unable to retrieve access token", 500);
        }
    }

    public function createMeeting(Request $request)
    {
        $client_id = '1001131123526-u0ldfuqhk22t84t5o6ro2gnlk6lifhbh.apps.googleusercontent.com';
        $client_secret = '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';
        $redirect_uri = 'https://e250-112-209-252-113.ngrok-free.app/api/v1/google-callback'; // Must match the redirect URI configured in your Google Cloud Console

        // Initialize the Google Client
        $client = new Google_Client();
        $client->setClientId($client_id);
        $client->setClientSecret($client_secret);
        $client->setRedirectUri($redirect_uri);
        $client->addScope('https://www.googleapis.com/auth/calendar'); // Scope for Google Calendar API

        // Set access token
        $client->setAccessToken($request->access_token);

        // Create a Google Calendar service object
        $service = new Google_Service_Calendar($client);

        // Define event details
        $event = new Google_Service_Calendar_Event([
            'summary' => 'Meeting Title',
            'description' => 'Meeting description',
            'start' => [
                'dateTime' => '2024-07-15T09:00:00-07:00', // Adjust start time as necessary
                'timeZone' => 'America/Los_Angeles',
            ],
            'end' => [
                'dateTime' => '2024-07-15T10:00:00-07:00', // Adjust end time as necessary
                'timeZone' => 'America/Los_Angeles',
            ],
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => uniqid(),
                    'conferenceSolutionKey' => [
                        'type' => 'hangoutsMeet'
                    ]
                ]
            ]
        ]);

        // Insert the event
        $calendarId = 'primary'; // Use 'primary' for the primary calendar of the authenticated user
        $event = $service->events->insert($calendarId, $event, ['conferenceDataVersion' => 1]);

        // Print the join URL for the Google Meet
        $meetLink = $event->getHangoutLink();

        return json_encode($meetLink, 200);
    }

    public function getGoogleLink()
    {
        $serviceAccountFilePath = base_path('gabay-guru.json'); // Path to your downloaded service account JSON key file
        // $redirect_uri = "https://oauth2.googleapis.com/token";
        // Initialize the Google Client
        $client = new Google_Client();
        $client->setApplicationName('Google Meet API PHP');
        $client->setScopes([Google_Service_Calendar::CALENDAR_EVENTS]);
        $client->setAuthConfig($serviceAccountFilePath);


        // Create OAuth 2.0 service
        $oauth = new Oauth2($client);

        $link = "";
        // Handle authorization flow
        if (!isset($_GET['code'])) {
            $auth_url = $client->createAuthUrl();
            $link = filter_var($auth_url, FILTER_SANITIZE_URL);
            return json_encode($link, 200);
        }

        return json_encode("Error: Unable to retrieve link", 500);
        // else {
        //     $client->fetchAccessTokenWithAuthCode($_GET['code']);
        //     $_SESSION['access_token'] = $client->getAccessToken();
        //     // $link = filter_var($redirect_uri, FILTER_SANITIZE_URL);
        //     // return Redirect::to($link);
        // }
    }

    public function getGoogleToken(Request $request)
    {
        $client_id = '1001131123526-u0ldfuqhk22t84t5o6ro2gnlk6lifhbh.apps.googleusercontent.com';
        $client_secret = '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';
        $authorization_code = $request->code;
        $redirect_uri = 'https://e250-112-209-252-113.ngrok-free.app/api/v1/google-callback'; // Must match the redirect URI configured in your Google Cloud Console

        $url = 'https://oauth2.googleapis.com/token';

        $client = new Client();
        $response = $client->request('POST', $url, [
            'form_params' => [
                'code' => $authorization_code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code',
            ],
        ]);
        $body = $response->getBody();
        $data = json_decode($body, true);

        if (isset($data['access_token'])) {
            // Access token obtained successfully
            $access_token = $data['access_token'];

            return json_encode($access_token, 200);
        } else {
            // Error handling if token retrieval fails

            return json_encode("Error: Unable to retrieve access token", 500);
        }
    }
}
