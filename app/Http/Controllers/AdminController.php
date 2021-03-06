<?php

namespace App\Http\Controllers;

use App\Events\User\PermissionCreated;
use App\Events\User\RoleCreated;
use App\Http\Requests\EditRoleRequest;
use App\Http\Requests\SavePermissionRequest;
use App\Http\Requests\SaveRoleRequest;
use App\Http\Requests\SettingAddRequest;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Setting;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{

    public function getConfigPage()
    {
        return view('adminlte.pages.admin.config');
    }

    public function getUserActivationPending()
    {
        $users = User::with('activation_token')
            ->where('active', 0)
            ->orderBy('created_at', 'desc')
            ->get();
        return view('adminlte.pages.admin.user-activation-pending', compact('users'));
    }

    /**
     * Getting settings page.
     *
     * @return $this
     */
    public function getSettingsPage()
    {
        $settings = Setting::all();

        return view('adminlte.pages.settings')->with('settings', $settings);
    }

    public function postHandleSettingsPageSave(Request $request)
    {
        $settings = $request->input('setting');

        foreach ($settings as $key => $value) {
            if ($value === "1") {
                $value = true;
            }
            if ($value === "0") {
                $value = false;
            }
            Setting::set($key, $value);
        }

        Setting::save();
        flash('Settings are now saved.');
        return redirect()->back();
    }

    public function postHandleSettingsPageAdd(SettingAddRequest $request)
    {
        $key = $request->input('name');
        $value = $request->input('value');

        if ($value === "1") {
            $value = true;
        }
        if ($value === "0") {
            $value = false;
        }

        Setting::set($key, $value);
        Setting::save();

        flash('The new setting is now added.');
        return redirect()->back();
    }

    /**
     * Get the page to see the list of roles and also the form to add a new role.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getManageRoles()
    {
        $roles = Role::orderBy('id', 'asc')->paginate(10);
        return view('adminlte.pages.admin.manage-roles', compact('roles'));
    }

    public function postSaveRoles(SaveRoleRequest $request)
    {
        $role = Role::create(['name' => $request->input('name')]);
        event(new RoleCreated($role));
        flash('Added a new Role');
        return redirect()->back();
    }

    /**
     * Get the edit role page.
     *
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getEditRole($id)
    {
        $role = Role::find($id);

        return view('adminlte.pages.admin.role-edit', compact('role'));
    }

    /**
     * Handle the edit role request.
     *
     * @param SaveRoleRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postUpdateRole(SaveRoleRequest $request)
    {
        $roleId = $request->input('id');

        if ($roleId == 1 || $roleId == 2) {
            abort(403, 'You cannot edit this role.');
        }

        $role = Role::find($request->input('id'));
        $role->name = $request->input('name');
        $role->save();

        flash('Role was updated');
        return redirect()->back();
    }

    public function getManagePermission()
    {
        $permissions = Permission::orderBy('id', 'asc')->paginate(10);
        return view('adminlte.pages.admin.manage-permissions', compact('permissions'));
    }

    public function postSavePermission(SavePermissionRequest $request)
    {
        $name = $request->input('name');

        $permission = Permission::create([
            'name' => $name
        ]);

        event(new PermissionCreated($permission));
        flash('New permission was created');
        return redirect()->back();
    }

    public function getEditPermission($id)
    {
        $permission = Permission::find($id);

        return view('adminlte.pages.admin.permission-edit', compact('permission'));
    }

    public function postUpdatePermission(SavePermissionRequest $request)
    {
        $permission = Permission::find($request->input('id'));
        $permission->name = $request->input('name');
        $permission->save();

        flash('Permission was updated');
        return redirect()->back();
    }

    public function importUser()
    {
        return view('adminlte.pages.admin.import-user');
    }

    public function handleImportUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required'
        ]);

        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withErrors($validator);
        }

        $file = $request->file('file');
        $csvData = file_get_contents($file);
        $rows = array_map("str_getcsv", explode("\n", $csvData));
        $header = array_shift($rows);

        foreach ($rows as $row) {
            $row = array_combine($header, $row);

            User::create([
                'name' => $row['name'],
                'email' => $row['email'],
                'password' => bcrypt(uniqid()),
                'active' => 1,
            ]);
        }

        flash('Users imported');
        return redirect()->back();
    }
}
