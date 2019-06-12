<?php

namespace App\Http\Controllers;

use App\SalesGroup;
use Illuminate\Http\Request;

class SalesGroupController extends Controller
{
    public function index(Request $request)
    {
        $salesGroups = SalesGroup::all();

        return response()->json(compact("salesGroups"), 200);
    }

    public function show(Request $request, $salesGroupId)
    {
        $salesGroup = SalesGroup::find($salesGroupId);

        return response()->json(compact("salesGroup"), 200);
    }

    public function store(Request $request)
    {
        $input = [
            "name" => $request->name,
            "start_date" => $request->start_date,
            "end_date" => $request->end_date,
        ];

        SalesGroup::create($input);

        $salesGroups = SalesGroup::all();

        return response()->json(compact("salesGroups"), 200);
    }

    public function update(Request $request, $salesGroupId)
    {
        $salesGroup = SalesGroup::find($salesGroupId);

        $salesGroup->name = $request->name;
        $salesGroup->start_date = $request->start_date;
        $salesGroup->end_date = $request->end_date;

        $salesGroup->save();

        $salesGroups = SalesGroup::all();

        return response()->json(compact("salesGroups"), 200);
    }

    public function delete(Request $request, $salesGroupId)
    {
        $salesGroups = SalesGroup::all();

        return response()->json(compact("salesGroups"), 200);
    }
}
