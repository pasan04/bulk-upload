<?php

namespace App\Http\Controllers;

  
use Illuminate\Http\Request;
use App\Exports\UsersExport;
use App\Imports\UsersImport;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\Importable;



class ImportExport extends Controller
{

    use Importable;

     /**
    * @return \Illuminate\Support\Collection
    */
    public function importExportView()
    {
       return view('importExport');
    }


   
    /**
    * @return \Illuminate\Support\Collection
    */
    public function export() 
    {
        return Excel::download(new UsersExport, 'users.xlsx');
    }
   
    /**
    * @return \Illuminate\Support\Collection
    */
    public function import() 
    {
        // (new UsersImport)->import(request()->file('file'), null, \Maatwebsite\Excel\Excel::XLSX);

        $import = new UsersImport();
        try{
            Excel::import($import,request()->file('import_file'));
        }catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            
            foreach ($failures as $failure) {
                $failure->row(); // row that went wrong
                $failure->attribute(); // either heading key (if using heading row concern) or column index
                $failure->errors(); // Actual error messages from Laravel validator
                $failure->values(); // The values of the row that has failed.
            }
        }
           
        return back();
    }
}
