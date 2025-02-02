<?php

namespace App\Imports;

use App\Security_user;
use App\User_body_mass;
use App\Institution_student;
use App\Import_mapping;
use App\Area_administrative;
use App\Nationality;
use App\Identity_type;
use App\Student_guardian;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;



class UsersImport implements ToCollection , WithStartRow , WithValidation , WithHeadingRow , WithMultipleSheets
{
    use Importable;


    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */

    public function startRow(): int
    {
        return 3;
    }


    public function headingRow(): int
    {
        return 2;
    }

    public function sheets(): array
    {
            return [
                // Select by sheet index
                1 => $this
            ];
    }


    public static  function getUniqueOpenemisId($options = [])
    {
        $prefix = '';

        $prefix =  DB::table('config_items')->where('code','=','openemis_id_prefix')->get();
        $prefix = explode(",", $prefix);
        $prefix = ($prefix[1] > 0) ? $prefix[0] : '';

        $latest = Security_user::orderBy('id', 'DESC')
            ->first();

        if (is_array($latest)) {
            $latestOpenemisNo = $latest['SecurityUser']['openemis_no'];
        } else {
            $latestOpenemisNo = $latest->openemis_no;
        }
        if (empty($prefix)) {
            $latestDbStamp = $latestOpenemisNo;
        } else {
            $latestDbStamp = substr($latestOpenemisNo, strlen($prefix));
        }

        $currentStamp = time();
        if ($latestDbStamp >= $currentStamp) {
            $newStamp = $latestDbStamp + 1;
        } else {
            $newStamp = $currentStamp;
        }

        return $prefix . $newStamp;
    }
    

    public function collection(Collection $rows)
    {
        
       $configStudentInfo = Import_mapping::getSheetColumns('Student.Info');
       $configStudentInstitution = Import_mapping::getSheetColumns('Student.Institution');
       $configStudentBmi = Import_mapping::getSheetColumns('Student.BMI');
      

       $this->validateRow($rows);

       foreach ($rows as $row) {

            $genderId = $row['gender_mf'] == 'M' ? 1 : 2;
            // $identityType = $row['identity_type'] == 'BC' ? 1 : 2;

            $AddressArea = Area_administrative::where('name', 'like', '%'.$row['address_area'].'%')->first();
            $BirthArea = Area_administrative::where('name', 'like', '%'.$row['birth_registrar_office_as_in_birth_certificate'].'%')->first();
            $nationalityId = Nationality::where('name','like','%'.$row['nationality'].'%')->first();
            $identityType = Identity_type::where('national_code','like','%'.$row['identity_type'].'%')->first();


            $date = \DateTime::createFromFormat("Y/m/d", $row['date_of_birth_ddmmyyyy']);
            $identityNUmber = $row['identity_number'];
            if($row['identity_type'] == 'BC'){
                $identityNUmber = $BirthArea->id . '' . $row['identity_number'] . '' . substr($date->format("yy"), -2) . '' . $date->format("m");
            }
           
            $openemisStudent = $this::getUniqueOpenemisId();



            \Log::debug('Security_user');
            $student =  Security_user::create([
                'username'=> $openemisStudent,
                'openemis_no'=>$openemisStudent,
                'first_name'=> $row['full_name'], // here we save full name in the column of first name. re reduce breaks of the system.
                'last_name' => genNameWithInitials($row['full_name']),
                'gender_id' => $genderId,
                'date_of_birth' => $date ,
                'address'   => $row['address'],
                'address_area_id'   => $AddressArea->id,
                'birthplace_area_id' => $BirthArea->id,
                'nationality_id' => $nationalityId->id,
                'identity_type_id' => $identityType->id,
                'identity_number' => $identityNUmber ,
                'created_user_id'=> 1,
                'created'=> now(),
                'is_student' => 1
            ]); 

             \Log::debug('Institution_student');
            Institution_student::create([
                'student_status_id' => 1,
                'student_id' => $student->id,
                'education_grade_id' => 1,
                'academic_period_id' => 2,
                'start_date' => '2019-01-01',
                'start_year' => '2019',
                'end_date' => '2019-12-31',
                'end_year' => '2019',
                'institution_id' => 80308,
                'created_user_id'=> 1,
                'created'=> now(),
                'admission_id' => '4555'
            ]);

            // convert Meeter to CM
            $hight = $row['height']/100;

            //calculate BMI 
            $bodyMass = ($row['weight']) / pow($hight,2);

             \Log::debug('User_body_mass');
            User_body_mass::create([
                'height' => $row['height'],
                'weight' => $row['weight'],
                'date' => $row['date'],
                'body_mass_index' => $bodyMass,
                'academic_period_id' => 1,
                'security_user_id' => $student->id,
                'created_user_id' => 1,
                'created' => now(),
            ]);

            //import father's information
            if(!empty($row['fathers_full_name'])){
                $AddressArea = Area_administrative::where('name', 'like', '%'.$row['fathers_address_area'].'%')->first();
                $nationalityId = Nationality::where('name','like','%'.$row['fathers_nationality'].'%')->first();
                $identityType = Identity_type::where('national_code','like','%'.$row['fathers_identity_type'].'%')->first();
                $date = \DateTime::createFromFormat("Y/m/d", $row['fathers_date_of_birth_ddmmyyyy']);
                $openemisFather = $this::getUniqueOpenemisId();
                $father  =   Security_user::create([
                        'username'=> $openemisFather,
                        'openemis_no'=>$openemisFather,
                        'first_name'=> $row['fathers_full_name'], // here we save full name in the column of first name. re reduce breaks of the system.
                        'last_name' => genNameWithInitials($row['fathers_full_name']),
                        'gender_id' => 1,
                        'date_of_birth' => $date ,
                        'address'   => $row['fathers_address'],
                        'address_area_id'   => $AddressArea->id,
                        'birthplace_area_id' => $BirthArea->id,
                        'nationality_id' => $nationalityId->id,
                        'identity_type_id' => $identityType->id,
                        'identity_number' => $row['fathers_identity_number'] ,
                        'created_user_id'=> 1,
                        'created'=> now(),
                        'is_guardian' => 1
                ]);

                Student_guardian::create([
                    'student_id' => $student->id,
                    'guardian_id' => $father->id,
                    'guardian_relation_id' => 1,
                    'created_user_id' => 1,
                    'created' => now()
                ]);
            }

            if(!empty($row['mothers_full_name'])){
                $AddressArea = Area_administrative::where('name', 'like', '%'.$row['mothers_address_area'].'%')->first();
                $nationalityId = Nationality::where('name','like','%'.$row['mothers_nationality'].'%')->first();
                $identityType = Identity_type::where('national_code','like','%'.$row['mothers_identity_type'].'%')->first();
                $date = \DateTime::createFromFormat("Y/m/d", $row['mothers_date_of_birth_ddmmyyyy']);
                $openemisMother = $this::getUniqueOpenemisId();
                $mother = Security_user::create([
                        'username'=> $openemisMother,
                        'openemis_no'=>$openemisMother,
                        'first_name'=> $row['mothers_full_name'], // here we save full name in the column of first name. re reduce breaks of the system.
                        'last_name' => genNameWithInitials($row['mothers_full_name']),
                        'gender_id' => 2,
                        'date_of_birth' => $date ,
                        'address'   => $row['mothers_address'],
                        'address_area_id'   => $AddressArea->id,
                        'birthplace_area_id' => $BirthArea->id,
                        'nationality_id' => $nationalityId->id,
                        'identity_type_id' => $identityType->id,
                        'identity_number' => $row['mothers_identity_number'] ,
                        'created_user_id'=> 1,
                        'created'=> now(),
                        'is_guardian' => 1
                ]);

                Student_guardian::create([
                    'student_id' => $student->id,
                    'guardian_id' => $mother->id,
                    'guardian_relation_id' => 2,
                    'created_user_id' => 1,
                    'created' => now()
                ]);
            }

             if(!empty($row['guardians_full_name'])){
                 $genderId = $row['guardians_gender_mf'] == 'M' ? 1 : 2;
                 $AddressArea = Area_administrative::where('name', 'like', '%'.$row['guardians_address_area'].'%')->first();
                 $nationalityId = Nationality::where('name','like','%'.$row['guardians_nationality'].'%')->first();
                 $identityType = Identity_type::where('national_code','like','%'.$row['guardians_identity_type'].'%')->first();
                 $date = \DateTime::createFromFormat("Y/m/d", $row['guardians_date_of_birth_ddmmyyyy']);
                 $openemisGuardian = $this::getUniqueOpenemisId();
                 $guardian =  Security_user::create([
                        'username'=> $openemisGuardian,
                        'openemis_no'=>$openemisGuardian,
                        'first_name'=> $row['guardians_full_name'], // here we save full name in the column of first name. re reduce breaks of the system.
                        'last_name' => genNameWithInitials($row['guardians_full_name']),
                        'gender_id' => $genderId,
                        'date_of_birth' => $date ,
                        'address'   => $row['guardians_address'],
                        'address_area_id'   => $AddressArea->id,
                        'birthplace_area_id' => $BirthArea->id,
                        'nationality_id' => $nationalityId->id,
                        'identity_type_id' => $identityType->id,
                        'identity_number' => $row['guardians_identity_number'] ,
                        'created_user_id'=> 1,
                        'created'=> now(),
                        'is_guardian' => 1
                ]);

                 Student_guardian::create([
                     'student_id' => $student->id,
                     'guardian_id' => $guardian->id,
                     'guardian_relation_id' => 3,
                     'created_user_id' => 1,
                     'created' => now()
                 ]);
            }

        }


    }


    public function validateRow($rows){
                Validator::make($rows->toArray(), [
                '*.full_name' => 'required|regex:/^[\pL\s\-]+$/u',
                '*.gender_mf' => 'required',
                '*.date_of_birth_ddmmyyyy' => 'required|date',
                '*.address' => 'required',
                '*.address_area' => 'required',
                '*.birth_registrar_office_as_in_birth_certificate' => 'required',
                '*.nationality' => 'required',
                '*.identity_type' => 'required',
                '*.identity_number' =>  'required|unique:security_users,identity_number', //'required|unique:security_users,identity_type_id',
                '*.academic_period' => 'required',
                '*.education_grade' => 'required',
                '*.height' => 'required',
                '*.weight' => 'required',
                '*.admission_no' => 'required',
                '*.start_date_ddmmyyyy' => 'required|date',
                '*.option_*' => 'required',
                '*.need_type' => 'required',
                '*.guardians_*' => 'required_without_all:*.fathers_*,*.mothers_*',
                '*.fathers_identity_number' => 'unique:security_users,identity_number',
                '*.mothers_identity_number' => 'unique:security_users,identity_number',
                '*.guardians_identity_number' => 'unique:security_users,identity_number'

        ])->validate();

    }

    public function rules(): array
    {
        return [
            // '*.0' => 'required',
            // '*.student_idleave_as_blank_for_new_entries' => Rule::in(['required']) ,
            // '*.full_name' => 'required'
            // '*.2' => 'required',
            // '*.3' => 'required',
            // '*.4' => 'required',
             // Above is alias for as it always validates in batches
            //  '*.1' => Rule::in(['patrick@maatwebsite.nl']),
             
            //  // Can also use callback validation rules
            //  '0' => function($attribute, $value, $onFailure) {
            //       if ($value !== 'Patrick Brouwers') {
            //            $onFailure('Name is not Patrick Brouwers');
            //       }
            //   }
        ];
    }
    
}
