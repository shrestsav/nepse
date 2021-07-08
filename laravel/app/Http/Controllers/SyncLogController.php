<?php

namespace App\Http\Controllers;

use App\Models\SyncLog;
use Illuminate\Http\Request;

class SyncLogController extends Controller
{   
    public function createSyncLog(Request $request)
    {
        $operationType = $request->operation_type;
        $type = $request->type;

        if($operationType == 'create'){
            $sync = SyncLog::create([
                'type'  => $type,
                'start' => date('Y-m-d H:i:s')
            ]);

            return response()->json($sync);

        } elseif($operationType == 'update') {
            $syncID = $request->id;
            $totalSynced = $request->total_synced;
            $totalTime = $request->total_time;

            $sync = SyncLog::where('id', $syncID)->where('type', $type)->update([
                'end'  => date('Y-m-d H:i:s'),
                'total_time' => $totalTime,
                'total_synced' => $totalSynced
            ]);

            return response()->json([
                'message' => 'Log Updated Successfully'
            ]);
        }

        return response()->json([
            'message' => 'Operation Type is Required'
        ], 403);

    }

    public function lastSyncLog()
    {
        $log = SyncLog::orderBy('created_at','DESC')->whereNotNull('end')->first();

        return response()->json($log);
    }
}
