<?php 

namespace Klaravel\Ntrust\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class MigrationCommand extends Command
{
    /**
     * Selected profile for generate
     * 
     * @var string
     */
    protected $profile;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'ntrust:migration {profile=user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a migration following the Ntrust specifications.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->profile = $this->argument('profile');

        if (!Config::has('ntrust.profiles.' . $this->profile)) {
            $this->error('Invalid profile. Please check profiles in config/ntrust.php');
            return self::FAILURE;
        }

        $this->laravel->view->addNamespace('ntrust', realpath(__DIR__ . '/../../views'));

        $rolesTable          = Config::get('ntrust.profiles.'. $this->profile .'.roles_table');
        $roleUserTable       = Config::get('ntrust.profiles.'. $this->profile .'.role_user_table');
        $permissionsTable    = Config::get('ntrust.profiles.'. $this->profile .'.permissions_table');
        $permissionRoleTable = Config::get('ntrust.profiles.'. $this->profile .'.permission_role_table');

        $this->info("Tables: $rolesTable, $roleUserTable, $permissionsTable, $permissionRoleTable");
        $this->comment("A migration that creates '$rolesTable', '$roleUserTable', '$permissionsTable', '$permissionRoleTable' tables will be created in database/migrations directory");

        if ($this->confirm("Proceed with the migration creation? [Yes|no]", true)) {

            $this->info("Creating migration...");

            if ($this->createMigration($rolesTable, $roleUserTable, $permissionsTable, $permissionRoleTable)) {
                $this->info("Migration successfully created!");
                return self::SUCCESS;
            } else {
                $this->error("Couldn't create migration. Check write permissions in database/migrations directory.");
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    protected function createMigration($rolesTable, $roleUserTable, $permissionsTable, $permissionRoleTable)
    {
        $migrationFile = base_path("database/migrations") . "/" . date('Y_m_d_His') . "_create_{$this->profile}_ntrust_setup_tables.php";

        $usersTable  = Config::get('ntrust.profiles.' . $this->profile . '.table');
        $userModel   = Config::get('ntrust.profiles.' . $this->profile . '.model');
        $userKeyName = (new $userModel())->getKeyName();
        $profile = $this->profile;

        $data = compact('rolesTable', 'roleUserTable', 'permissionsTable', 'permissionRoleTable', 'usersTable', 'userKeyName', 'profile');

        $output = $this->laravel->view->make('ntrust::generators.migration')->with($data)->render();

        if (!file_exists($migrationFile) && $fs = fopen($migrationFile, 'x')) {
            fwrite($fs, $output);
            fclose($fs);
            return true;
        }

        return false;
    }
}
