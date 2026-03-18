<?php

namespace App\Http\Controllers;

use App\Models\Table;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function index()
    {
        return Table::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'location' => 'required|in:A,B,C,D',
            'number' => 'required|integer|unique:tables',
            'capacity' => 'required|integer|min:1',
        ]);

        return Table::create($request->all());
    }

    public function show(Table $table)
    {
        return $table;
    }

    public function update(Request $request, Table $table)
    {
        $request->validate([
            'location' => 'sometimes|in:A,B,C,D',
            'number' => 'sometimes|integer|unique:tables,number,' . $table->id,
            'capacity' => 'sometimes|integer|min:1',
        ]);

        $table->update($request->all());
        return $table;
    }

    public function destroy(Table $table)
    {
        $table->delete();
        return response()->json(['message' => 'Table deleted']);
    }
}
