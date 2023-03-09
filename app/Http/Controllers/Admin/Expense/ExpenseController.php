<?php

namespace App\Http\Controllers\Admin\Expense;

use App\Helper\Account as HelperAccount;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Admin\Settings\Staff;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin\Account\Account;
use App\Models\Admin\Expense\Expense;
use Yajra\DataTables\Facades\DataTables;
use App\Models\Admin\Account\Transaction;
use App\Models\Admin\Expense\ExpenseCategory;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            return view('admin.expense.index');
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
            $expense_num = Expense::count();
            $staffs = Staff::where('status', 1)->orderby('id', 'desc')->get();
            $accounts = Account::where('status', 1)->orderby('id', 'desc')->get();
            $categories = ExpenseCategory::where('status', 1)->orderby('id', 'desc')->get();
            return view('admin.expense.create', compact('categories', 'expense_num', 'staffs', 'accounts'));
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
            'date.required' => 'Enter date',
            'account_id.required' => 'Select account name',
        );

        $this->validate($request, array(
            'account_id'=> 'required',
            'expense_number' => 'required|string|unique:expenses,expense_number',
            'image.*' => 'max:2048'
        ), $messages);

        $picture = [];
        if ($request->hasfile('image')) {
            foreach ($request->file('image') as $image) {
                $name = $image->getClientOriginalName();
                $image->move(public_path() . '/img/', $name);
                $picture[] = $name;
                array_push($picture);
            }
        }

        DB::beginTransaction();

        try {
            $sum = 0;
            $balance = HelperAccount::postBalance($request->account_id);
            foreach ($request->amount as $amount) {
                $sum = $amount + $sum;
            }
            if ($balance > $sum) {
            $data = new Expense();
            $data->expense_number = $request->expense_number;
            $data->date = Carbon::parse($request->date)->format('Y-m-d');
            $data->account_id = $request->account_id;
            $data->category_id = json_encode($request->category_id);
            $data->image = json_encode($picture);
            $data->amount = json_encode($request->amount);
            $data->all_amount = $request->all_amount;
            $data->adjust_bill = $request->adjust_bill;
            $data->adjust_amount = $request->adjust_amount;
            $data->total_amount = $request->total_amount;
            $data->description = $request->description;
            $data->save();

            $account = new Transaction();
            $account->expense_id  = $data->id;
            $account->account_id  = $data->account_id;
            $account->transaction_amount = $data->total_amount;
            $account->transaction_date = Carbon::parse($data->date)->format('Y-m-d');
            $account->transaction_type = 1;
            $account->payment_type = 1;
            $account->transaction_reason = 'Expense';
            $account->save();

            DB::commit();

            return redirect()->route('admin.expense.index')
                ->with('message', 'Expense created successfully');
            } else {
                return redirect()->back()->with('error', "You don't have enough balance.");
            }
        } catch (\Exception $exception) {
            DB::rollback();
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    public function expenses(Request $request)
    {
        try {
            if ($request->ajax()) {

                $data = Expense::with('excategory', 'accounts')->orderBy('id', 'desc')->get();

                return Datatables::of($data)
                    ->addColumn('action', function (Expense $data) {
                        if (Auth::user()->can('expense_edit')) {
                            $invoice = '<a href="' . route('admin.expense-invoice', $data->id) . ' " class="btn btn-sm btn-secondary" title="Print"><i class="fa fa-print"></i></a>';
                        } else {
                            $invoice = "";
                        }
                        if (Auth::user()->can('expense_show')) {
                            $show = ' <a href="' . route('admin.expense.show', $data->id) . ' " class="btn btn-sm btn-primary" title="Show"><i class="fa fa-eye"></i></a>';
                        } else {
                            $show = "";
                        }
                        if (Auth::user()->can('expense_edit')) {
                            $edit = ' <a href="' . route('admin.expense.edit', $data->id) . ' " class="btn btn-sm btn-info" title="Edit"><i class="fa fa-edit"></i></a>';
                        } else {
                            $edit = " ";
                        }
                        if (Auth::user()->can('expense_delete')) {
                            $delete = ' <button id="messageShow" class="btn btn-sm btn-danger btn-delete" data-remote=" ' . route('admin.expense.destroy', $data->id) . ' " title="Delete" ><i class="fa fa-trash-alt"></i></button>';
                        } else {
                            $delete = " ";
                        }
                        return $invoice . $show . $edit . $delete;
                    })

                    ->addIndexColumn()
                    ->rawColumns(['action', 'description'])
                    ->toJson();
            }
        } catch (\Exception $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $categories  = ExpenseCategory::orderBy('id', 'desc')->where('status', 1)->get();
            $data = Expense::with('excategory', 'accounts')->findOrFail($id);
            return view('admin.expense.show', compact('data', 'categories'));
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
            $data = Expense::findOrFail($id);
            $staffs = Staff::where('status', 1)->orderby('id', 'desc')->get();
            $accounts = Account::where('status', 1)->orderby('id', 'desc')->get();
            $categories = ExpenseCategory::where('status', 1)->orderby('id', 'desc')->get();
            return view('admin.expense.edit', compact('categories', 'data', 'staffs', 'accounts'));
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
            'date.required' => 'Enter date',
            'account_id.required' => 'Select account name',
        );

        $this->validate($request, array(
            'expense_number' => 'required|string|unique:expenses,expense_number,' . $id,
            'account_id' => 'required',
            'image.*' => 'max:2048'
        ), $messages);


        $picture = [];
        if ($request->hasfile('image')) {
            foreach ($request->file('image') as $image) {
                $name = $image->getClientOriginalName();
                $image->move(public_path() . '/img/', $name);
                $picture[] = $name;
                array_push($picture);
            }
        }

        DB::beginTransaction();

        try {
            $sum = 0;
            $balance = HelperAccount::postBalance($request->account_id);
            foreach ($request->amount as $amount) {
                $sum = $amount + $sum;
            }
            if ($balance > $sum) {
                $data = Expense::findOrFail($id);
                $data->expense_number = $request->expense_number;
                $data->date = Carbon::parse($request->date)->format('Y-m-d');
                $data->category_id = json_encode($request->category_id);

                if ($request->image != NULL) {
                    $data->image = json_encode($picture);
                }

                $data->amount = json_encode($request->amount);
                $data->all_amount = $request->all_amount;
                $data->adjust_bill = $request->adjust_bill;
                $data->adjust_amount = $request->adjust_amount;
                $data->total_amount = $request->total_amount;
                $data->account_id = $request->account_id;
                $data->description = $request->description;

                $data->update();

                $account = Transaction::where('expense_id', $id)->first();
                $account->expense_id  = $data->id;
                $account->account_id  = $data->account_id;
                $account->transaction_amount = $data->total_amount;
                $account->transaction_date = Carbon::parse($data->date)->format('Y-m-d');
                $account->update();

                DB::commit();

                return redirect()->route('admin.expense.index')
                    ->with('message', 'Expense updated successfully');
            } else {
                return redirect()->back()->with('error', "You don't have enough balance.");
            }
        } catch (\Exception $exception) {
            DB::rollback();
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
            $data = Expense::findOrFail($id);
            $data->delete();
            return back()->with('message', 'Data deleted successfully');
        } catch (\Exception $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }

    public function ChangeStatus(Request $request)
    {
        $id = $request->id;
        $status_check   = Expense::findOrFail($id);
        $status         = $status_check->status;

        if ($status == 1) {
            $status_update = 0;
        } else {
            $status_update = 1;
        }

        $data           = array();
        $data['status'] = $status_update;
        Expense::where('id', $id)->update($data);
        if ($status_update == 1) {
            return "success";
        } else {
            return "failed";
        }
    }

    public function invoice($id)
    {
        try {
            $data = Expense::with('excategory')->findOrFail($id);
            return view('admin.expense.invoice', compact('data'));
        } catch (\Exception $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
    }
}
