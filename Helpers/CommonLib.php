<?php


namespace App\Helpers;


use App\Repositories\RoleRepository;
use App\Repositories\RoleUserRepository;
use App\Repositories\SchoolYearRepository;
use Illuminate\Support\Facades\Auth;

class CommonLib
{
    public static function getConfigZalo()
    {
        return array(
            'app_id' => env('ZALO_APP_ID', null),
            'app_secret' => env('ZALO_APP_SECRET', null),
            'callback_url' => env('ZALO_CALLBACK_URL', 'https://www.callback.com')
        );
    }

    public static function generateRandomString($length = 10, $prefix = null)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return (!empty($prefix)) ? $prefix . $randomString : $randomString;
    }

    public static function initDataLogin($state, $user_info)
    {
        $role_repository = new RoleRepository();
        $role_user_repository = new RoleUserRepository();
        if (isset($state) && $state == 0)
            $role_code = ROLE_STUDENT;
        elseif ($state == 1)
            $role_code = ROLE_TEACHER;
        else
            $role_code = ROLE_ADMIN;

        $role_info = $role_repository->getData(['code' => $role_code])->first();

        if (empty($role_info))
            return array();
        $role_users = $role_user_repository->getData(['user_id' => $user_info->id, 'role_id' => $role_info->id]);
        $role_user_info = array();
        $count_moet_unit = 0;
        foreach ($role_users as $role_user) {
            if (empty($role_user->moet_unit_id)) {
                $count_moet_unit++;
                continue;
            }

            $role_user_info = $role_user;
        }

        //Truong user khong co moet_unit_id nao, bo qua viec check moet unit id
        if ($count_moet_unit == count($role_users))
            $role_user_info = $role_users->first();

        if (empty($role_user_info))
            return array();

        return self::initSessionData($role_info->id, $role_user_info->moet_unit_id);
    }

    public static function initSessionData($role_id, $moet_unit_id, $school_year_id = null)
    {
        $school_year_repository = new SchoolYearRepository();
        if (empty($school_year_id)) {
            $schoolYearInfo = $school_year_repository->getCurrentSchoolYear(Auth::user()->tenant_id);
            if (empty($schoolYearInfo))
                return array();
            $school_year_id = $schoolYearInfo->id;
        }

        $conditions = array();
        $conditions['user_id'] = Auth::id();
        $conditions['role_id'] = $role_id;
        $conditions['moet_unit_id'] = $moet_unit_id;
        $role_user_repository = new RoleUserRepository();

        $role_users = $role_user_repository->getData($conditions, ['role', 'moetUnit']);
        $roles = array();
        $moet_units = array();
        foreach ($role_users as $role_user) {
            if (empty($role_user->role))
                continue;

            if ($role_user->role->status != 1)
                continue;

            $item = array();
            $item['id'] = $role_user->role_id;
            $item['name'] = $role_user->role->name;
            $item['code'] = $role_user->role->code;
            $roles[$role_user->role_id] = $item;

            if (empty($role_user->moetUnit))
                continue;

            $item_moet_unit = array();
            $item_moet_unit['id'] = $role_user->moet_unit_id;
            $item_moet_unit['code'] = $role_user->moetUnit->code;
            $item_moet_unit['name'] = $role_user->moetUnit->name;
            $item_moet_unit['path'] = $role_user->moetUnit->path;
            $item_moet_unit['moet_level'] = $role_user->moetUnit->moet_level;
            $item_moet_unit['grade_level_id'] = $role_user->moetUnit->grade_level_id;
            $moet_units[$role_user->moet_unit_id] = $item_moet_unit;
        }

        //List school year
        $school_year_db = $school_year_repository->getData();
        session()->put('current_role_info', $role_id);
        session()->put('current_moet_unit_id', $moet_unit_id);
        session()->put('school_year_id', $school_year_id);
        session()->put('roles', array_values($roles));
        session()->put('moet_units', array_values($moet_units));

        $school_years = array();
        foreach ($school_year_db as $school_year) {
            $school_years[] = self::transformSchoolYear($school_year);
        }
        return ['roles' => array_values($roles), 'moet_units' => array_values($moet_units), 'school_year_id' => $school_year_id, 'role_id' => $role_id, 'moet_unit_id' => $moet_unit_id, 'school_years' => $school_years];
    }

    public static function transformSchoolYear($school_year)
    {
        return [
            'id' => $school_year->id,
            'tenant_id' => $school_year->tenant_id,
            'code' => $school_year->code,
            'name' => $school_year->name,
            'start_date' => $school_year->start_date,
            'end_date' => $school_year->end_date,
            'status' => $school_year->status
        ];
    }
}
