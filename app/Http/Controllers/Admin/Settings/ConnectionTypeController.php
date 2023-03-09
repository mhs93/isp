<?php

namespace App\Http\Controllers\Admin\Settings;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Admin\Settings\ConnectionType;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Str;

class ConnectionTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            return view('admin.settings.connection_type.index');
        } catch (\Exception $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    //*** JSON Request
    public function connection(Request $request)
    {
        try {
            $data = ConnectionType::orderBy('id', 'desc')->get();
            if ($request->ajax()) {
                return Datatables::of($data)
                    ->addColumn('status', function ($data) {
                        if (Auth::user()->can('connection_status')) {
                            $button = ' <div class="custom-control custom-switch">';
                            $button .= ' <input type="checkbox" class="custom-control-input changeStatus" id="customSwitch' . $data->id . '" getId="' . $data->id . '" name="status"';

                            if ($data->status == 1) {

                                $button .= "checked";
                            }
                            $button .= '><label for="customSwitch' . $data->id . '" class="custom-control-label" for="switch1"></label></div>';
                            return $button;
                        } else {
                            return " -- ";
                        }
                    })

                    ->addColumn('description', function ($data) {
                        $result = isset($data->description) ? $data->description : '--' ;
                        return Str::limit( $result, 20) ;
                    })

                    ->addColumn('action', function (ConnectionType $data) {
                        if (Auth::user()->can('connection_edit')) {
                            $edit = '<a href="' . route('admin.connection.edit', $data->id) . ' " class="btn btn-sm btn-info" title="Edit"><i class="fa fa-edit"></i></a> ';
                        } else {
                            $edit = "";
                        }
                        if (Auth::user()->can('connection_delete')) {
                            $delete = ' <button id="messageShow" class="btn btn-sm btn-danger btn-delete" data-remote=" ' . route('admin.connection.destroy', $data->id) . ' " title="Delete"><i class="fa fa-trash-alt"></i></button>';
                        } else {
                            $delete = " ";
                        }
                        return $edit.$delete;
                    })

                    ->rawColumns(['status', 'action', 'description'])
                    ->toJson();
            }
        } catch (\Exception $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        try {
            return view('admin.settings.connection_type.create');
        } catch (\Exception $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $messages = array(
            'name.required' => 'Please enter an connection name',
            'code.required' => 'Please enter an connection code',
        );

        $this->validate($request, array(
            'name' => 'required|string|unique:connection_types,name,NULL,id,deleted_at,NULL',
            'code' => 'required|string|unique:connection_types,code,NULL,id,deleted_at,NULL',
        ), $messages);

        try {
            $data = new ConnectionType();

            $data->name = $request->name;
            $data->code = $request->code;
            $data->status = $request->status;
            $data->description = $request->description;
            $data->save();

            return redirect()->route('admin.connection.index')
                ->with('message', 'Connection created successfully');
        } catch (\Exception $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        try {
            $data = ConnectionType::findOrFail($id);
            return view('admin.settings.connection_type.edit', compact('data'));
        } catch (\Exception $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    public function code()
    {
        try {
            do {
                $code = random_int(10000, 99999);
            } while (ConnectionType::where("code", "=", $code)->exists());
            return $code;
        } catch (\Exception $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function update(Request $request, $id)
    {
        $messages = array(
            'name.required' => 'Please enter an area name',
            'code.required' => 'Please enter an area code',
        );

        $this->validate($request, array(
            'name' => 'required|unique:connection_types,name,' . $id . ',id,deleted_at,NULL',
            'code' => 'required|unique:connection_types,code,' . $id . ',id,deleted_at,NULL',
        ), $messages);

        try {
            $data = ConnectionType::findOrFail($id);
            $data->name = $request->name;
            $data->code = $request->code;
            $data->status = $request->status;
            $data->description = $request->description;
            $data->update();

            return redirect()->route('admin.connection.index')
                ->with('message', 'Connection updated successfully');
        } catch (\Exception $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function destroy($id)
    {
        try {
            $data = ConnectionType::findOrFail($id);
            $data->delete();
            return back()->with('message', 'Connection deleted successfully');
        } catch (\Exception $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    public function StatusChange(Request $request)
    {
        $id = $request->id;
        $status_check   = ConnectionType::findOrFail($id);
        $status         = $status_check->status;

        if ($status == 1) {
            $status_update = 0;
        } else {
            $status_update = 1;
        }

        $data           = array();
        $data['status'] = $status_update;
        ConnectionType::where('id', $id)->update($data);

        if ($status_update == 1) {
            return "success";
        } else {
            return "failed";
        }
    }
}
