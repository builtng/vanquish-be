<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PsgReflection;
use Illuminate\Http\Request;

class PsgReflectionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = PsgReflection::with('counsellor:id,name');

        if ($user->role === 'counsellor' && $user->training_counsellor_id) {
            $query->where('training_counsellor_id', $user->training_counsellor_id);
        } elseif ($user->role !== 'admin' && $user->role !== 'staff') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $reflections = $query->orderBy('attendance_date', 'desc')->get();
            
        return response()->json($reflections);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        
        if ($user->role !== 'counsellor' || !$user->training_counsellor_id) {
            return response()->json(['message' => 'Only counsellors can submit discussions'], 403);
        }

        $content = $request->input('discussion') ?? $request->input('reflection');
        if (empty($content)) {
            return response()->json(['message' => 'The discussion field is required.'], 422);
        }

        $request->validate([
            'attendance_date' => 'required|date',
        ]);

        $reflection = PsgReflection::create([
            'training_counsellor_id' => $user->training_counsellor_id,
            'reflection' => $content,
            'attendance_date' => $request->attendance_date,
            'status' => 'submitted',
        ]);

        return response()->json($reflection, 201);
    }
}
