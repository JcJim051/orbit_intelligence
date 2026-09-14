<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMeetingRequest;
use App\Models\DriveConnection;
use App\Models\Meeting;
use App\Models\User;
use App\Services\MeetingIngestionService;
use Illuminate\Support\Str;

class WebMeetingController extends Controller
{
    public function index()
    {
        return view('meetings.index');
    }

    public function create()
    {
        return view('meetings.create', [
            'reviewers' => User::query()->whereIn('role', ['reviewer', 'admin'])->where('active', true)->orderBy('name')->get(),
            'connections' => DriveConnection::query()->where('active', true)->orderBy('label')->get(),
        ]);
    }

    public function store(StoreMeetingRequest $request, MeetingIngestionService $service)
    {
        $meeting = $service->ingest($request->user(), $request->file('audio'), $request->validated(), (string) Str::uuid());

        return redirect()->route('meetings.show', $meeting)->with('status', 'Audio recibido y enviado a procesamiento.');
    }

    public function show(Meeting $meeting)
    {
        $this->authorize('view', $meeting);

        return view('meetings.show', compact('meeting'));
    }
}
