<?php

namespace App\Http\Controllers\Admin\Settings;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\Settings\Area;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;

class AreaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            return view('admin.settings.area.index');
        } catch (\Exception $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    //*** JSON Request
    public function area(Request $request)
    {
        try {
            if ($request->ajax()) {

                $data = Area::orderBy('id', 'desc')->get();
                return Datatables::of($data)
                    ->addColumn('status', function ($data) {
                        if (Auth::user()->can('area_status')) {
                        $button = ' <div class="custom-control custom-switch">';
                        $button .= ' <input type="checkbox" class="custom-control-input changeStatus" id="customSwitch' . $data->id . '" getId="' . $data->id . '" name="status"';

                        if ($data->status == 1) {
                            $button .= "checked";
                        }

                        $button .= '><label for="customSwitch' . $data->id . '" class="custom-control-label" for="switch1"></label></div>';
                        return $button;
                        }
                    })

                    ->addColumn('description', function ($data) {
                        $result = isset($data->description) ? $data->description : '--' ;
                        return Str::limit( $result, 20) ;
                    })

                    ->addColumn('action', function (Area $data) {
                        if (Auth::user()->can('area_edit')) {
                           $edit='<a href="' . route('admin.area.edit', $data->id) . ' " class="btn btn-sm btn-info" title="Edit"><i class="fa fa-edit"></i></a> ';

                        }
                        else{
                            $edit = " ";
                        }
                        if (Auth::user()->can('area_delete')) {
                            $delete = ' <button id="messageShow" class="btn btn-sm btn-danger btn-delete" data-remote=" ' . route('admin.area.destroy', $data->id) . ' " title="Delete"><i class="fa fa-trash-alt"></i></button>';

                        }
                        else{
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
            return view('admin.settings.area.create');
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
            'name.required' => 'Please enter an area name',
            'code.required' => 'Please enter an area code',
        );

        $this->validate($request, array(
            'name' => 'required|string|unique:areas,name,NULL,id,deleted_at,NULL',
            'code' => 'required|string|unique:areas,code,NULL,id,deleted_at,NULL',
        ), $messages);

        try {
            $data = new Area();

            $data->name = $request->name;
            $data->code = $request->code;
            $data->status = $request->status;
            $data->description = $request->description;
            $data->save();

            return redirect()->route('admin.area.index')
                ->with('message', 'Area created successfully');
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
    public function edit(Area $area)
    {
        try {
            return view('admin.settings.area.edit', compact('area'));
        } catch (\Exception $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    public function code()
    {
        try {
            do {
                $code = random_int(10000, 99999);
            } while (Area::where("code", "=", $code)->exists());
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

    public function update(Request $request, Area $area)
    {
        $messages = array(
            'name.required' => 'Please enter an area name',
            'code.required' => 'Please enter an area code',
        );

        $this->validate($request, array(
            'name' => 'required|unique:areas,name,' . $area->id . ',id,deleted_at,NULL',
            'code' => 'required|unique:areas,code,' . $area->id . ',id,deleted_at,NULL',
        ), $messages);

        try {
            $area->name = $request->name;
            $area->code = $request->code;
            $area->status = $request->status;
            $area->description = $request->description;
            $area->update();

            return redirect()->route('admin.area.index')
                ->with('message', 'Area updated successfully');
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

    public function destroy(Area $area)
    {
        try {
            $area->delete();
            return back()->with('message', 'Area deleted successfully');
        } catch (\Exception $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    public function StatusChange(Request $request)
    {
        $id = $request->id;
        $status_check   = Area::findOrFail($id);
        $status         = $status_check->status;

        if ($status == 1) {
            $status_update = 0;
        } else {
            $status_update = 1;
        }

        $data           = array();
        $data['status'] = $status_update;
        Area::where('id', $id)->update($data);
        if ($status_update == 1) {
            return "success";
        } else {
            return "failed";
        }
    }
}
