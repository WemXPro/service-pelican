<?php

namespace App\Services\Pelican;

use App\Services\ServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Package;
use App\Models\Order;

class Service implements ServiceInterface
{
    /**
     * Unique key used to store settings
     * for this service.
     *
     * @return string
     */
    public static $key = 'pelican';

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Returns the meta data about this Server/Service
     *
     * @return object
     */
    public static function metaData(): object
    {
        return (object)
        [
            'display_name' => 'Pelican',
            'author' => 'WemX',
            'version' => '1.0.0',
            'wemx_version' => ['dev', '>=1.8.0'],
        ];
    }

    /**
     * Define the default configuration values required to setup this service
     * i.e host, api key, or other values. Use Laravel validation rules for
     *
     * Laravel validation rules: https://laravel.com/docs/10.x/validation
     *
     * @return array
     */
    public static function setConfig(): array
    {
        // Check if the URL ends with a slash
        $doesNotEndWithSlash = function ($attribute, $value, $fail) {
            if (preg_match('/\/$/', $value)) {
                return $fail('Hostname URL must not end with a slash "/". It should be like https://panel.example.com');
            }
        };

        return [
            [
                "key" => "pelican::hostname",
                "name" => "Hostname",
                "description" => "Hostname of your Pelican panel i.e https://panel.example.com",
                "type" => "url",
                "rules" => ['required', 'active_url', $doesNotEndWithSlash], // laravel validation rules
            ],
            [
                "key" => "encrypted::pelican::api_key",
                "name" => "API Key",
                "description" => "API Key of your Pelican panel",
                "type" => "password",
                "rules" => ['required', 'starts_with:peli_'], // laravel validation rules
            ],
        ];
    }

    /**
     * Define the default package configuration values required when creatig
     * new packages. i.e maximum ram usage, allowed databases and backups etc.
     *
     * Laravel validation rules: https://laravel.com/docs/10.x/validation
     *
     * @return array
     */
    public static function setPackageConfig(Package $package): array
    {
        $config = [
            [
                "col" => "col-4",
                "key" => "database_limit",
                "name" => "Database Limit",
                "description" => "The total number of databases a user is allowed to create for this server on Pterodactyl Panel.",
                "type" => "number",
                "min" => 0,
                "rules" => ['required'], // laravel validation rules
                'is_configurable' => true,
            ],
            [
                "col" => "col-4",
                "key" => "backup_limit_size",
                "name" => "Backup Size Limit in MB",
                "description" => "The total size of backups that can be created for this server Pterodactyl Panel.",
                "type" => "number",
                "min" => 0,
                "rules" => ['required', 'numeric'],
                'is_configurable' => true,
            ],
            [
                "col" => "col-4",
                "key" => "cpu_limit",
                "name" => "CPU Limit in %",
                "description" => "If you do not want to limit CPU usage, set the value to0. To use a single thread set it to 100%, for 4 threads set to 400% etc",
                "type" => "number",
                "min" => 0,
                "rules" => ['required'],
                'is_configurable' => true,
            ],
            [
                "col" => "col-4",
                "key" => "memory_limit",
                "name" => "Memory Limit in MB",
                "description" => "The maximum amount of memory allowed for this container. Setting this to 0 will allow unlimited memory in a container.",
                "type" => "number",
                "min" => 0,
                "rules" => ['required'],
                'is_configurable' => true,
            ],
            [
                "col" => "col-4",
                "key" => "disk_limit",
                "name" => "Disk Limit in MB",
                "description" => "The maximum amount of memory allowed for this container. Setting this to 0 will allow unlimited memory in a container.",
                "type" => "number",
                "min" => 0,
                "rules" => ['required'],
                'is_configurable' => true,
            ],
            [
                "col" => "col-4",
                "key" => "cpu_pinning",
                "name" => "CPU Pinning (optional)",
                "description" => __('admin.cpu_pinning_desc'),
                "type" => "text",
                "rules" => ['nullable'],
            ],
            [
                "col" => "col-4",
                "key" => "swap_limit",
                "name" => __('admin.swap'),
                "description" => __('admin.swap_desc'),
                "type" => "number",
                "default_value" => 0,
                "rules" => ['required'],
            ],
            [
                "col" => "col-4",
                "key" => "block_io_weight",
                "name" => __('admin.block_io_weight'),
                "description" =>  __('admin.block_io_weight_desc'),
                "type" => "number",
                "default_value" => 500,
                "rules" => ['required'],
            ],
            [
                "key" => "node_id",
                "name" => "Node ID",
                "description" =>  "The node on which the server should be deployed.",
                "type" => "numbers",
                "rules" => ['required', 'numeric'],
                'is_configurable' => true,
            ],
            [
                "key" => "egg_id",
                "name" => "Egg ID",
                "description" =>  "Egg ID of the server you want to use for this package. You can find the egg ID by going to the egg page and looking at the URL. It will be the number at the end of the URL.",
                "type" => "text",
                "default_value" => 3, // paper minecraft
                'save_on_change' => true,
                "rules" => ['required', 'numeric'],
            ],
            [
                "key" => "dedicated_IP",
                "name" => "Dedicated IP",
                "description" =>  "If you want to assign a dedicated IP to this server, set this to true.",
                "type" => "bool",
                "rules" => ['boolean'],
                'is_configurable' => true,
            ],
        ];

        return $config;
    }

    /**
     * Define the checkout config that is required at checkout and is fillable by
     * the client. Its important to properly sanatize all inputted data with rules
     *
     * Laravel validation rules: https://laravel.com/docs/10.x/validation
     *
     * @return array
     */
    public static function setCheckoutConfig(Package $package): array
    {
        return [];
    }

    /**
     * Define buttons shown at order management page
     *
     * @return array
     */
    public static function setServiceButtons(Order $order): array
    {
        return [
            [
                "name" => "Login to Game Panel",
                "color" => "primary",
                "href" => settings('pelican::hostname'),
                "target" => "_blank", // optional
            ],
        ];
    }

    /**
     * Test API connection
     */
    public static function testConnection()
    {
        try {
            Service::makeRequest('/api/application/users');
        } catch (\Exception $e) {
            return redirect()->back()->withError($e->getMessage());
        }

        return redirect()->back()->withSuccess('Successfully connected to Pelican API');
    }

    /**
     * Make API request to Pelican API
     */
    public static function makeRequest($endpoint, $method = 'get', $data = [])
    {
        $method = strtolower($method);

        if (!in_array($method, ['get', 'post', 'put', 'delete'])) {
            throw new \Exception('Invalid method');
        }

        $response = Http::withToken(settings('encrypted::pelican::api_key'))
            ->$method(settings('pelican::hostname') . $endpoint, $data);

        if ($response->failed() OR $response->json() === null) {
            dd($response, $response->json(), $endpoint, $data, $method);
            throw new \Exception("Failed to connect to Pelican API at endpoint: $endpoint with status code: {$response->status()} and response: {$response->body()}");
        }

        return $response;
    }

    /**
     * Change the Pelican password
     */
    public function changePassword(Order $order, string $newPassword)
    {

    }

    /**
     * This function is responsible for creating an instance of the
     * service. This can be anything such as a server, vps or any other instance.
     *
     * @return void
     */
    public function create(array $data = [])
    {
        // define variables
        $pelicanUserId = $this->createPelicanUser();
        dd($pelicanUserId);
        $order = $this->order;
        $package = $order->package;

        // Create the server on Pelican panel
        $createServerResponse = Service::makeRequest("/api/application/servers", 'post', [
            //'external_id' => "wemx{$order->id}",
            'name' => $package->name,
            'user' => 1,
            'egg' => 3,
            'allocation' => 4,
            'startup' => 'java -Xms128M -XX:MaxRAMPercentage=95.0 -Dterminal.jline=false -Dterminal.ansi=true -jar {{SERVER_JARFILE}}',
            'docker_image' => 'ghcr.io/parkervcp/yolks:java_21',
            'environment' => [
                'SERVER_JARFILE' => 'server.jar',
                'BUILD_NUMBER' => 'latest',
            ],
            "limits" => [
                "memory" => 0,
                "swap" => 0,
                "disk" => 0,
                "io" => 0,
                "cpu" => 0,
            ],
            "feature_limits" => [
                "databases" => 0,
                "allocations" => 0,
                'backups' => 0,
            ],
            'deploy' => [
                'locations' => [2],
                'dedicated_ip' => false,
                'port_range' => [25566, 25577],
            ],
            "start_on_completion" => true,
            "skip_scripts" => false,
            "oom_disabled" => false,
            "swap_disabled" => false,
        ]);

        dd($createServerResponse, $createServerResponse->json(), $createServerResponse->status());
    }

    /**
     * Create the user on Pelican panel and store the data locally
     * If the user already exists, return the user id on Pelican panel
     *
     * @return int
     */
    private function createPelicanUser(): int
    {
        $user = $this->order->user;

        if($this->order->hasExternalUser()) {
            return $this->order->getExternalUser()->external_id;
        }

        try {
            // Attempt to find the user on Pelican with the external id
            $userEmailResponse = Service::makeRequest("/api/application/users", 'get', [
                'filter[email]' => $user->email,
            ]);

            // if api returns a user, store the user data locally and return the user id
            if(isset($userEmailResponse['data'][0])) {
                $this->storePelicanUserLocally(
                    $userEmailResponse['data'][0]['attributes']
                );

                return $userEmailResponse['data'][0]['attributes']['id'];
            }
        } catch(\Exception $e) {
            
        }

        // attempt to create the user on Pelican
        $randomPassword = Str::random(16);
        $createUserResponse = Service::makeRequest("/api/application/users", 'post', [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'username' => $user->username . $user->id, // username must be unique
            'password' => $randomPassword,
        ]);

        // email the user their Pelican panel credentials
        $this->emailPterodactylUserCredentials(
            $user->email,
            $randomPassword
        );

        // store the user data locally
        $pelicanUserData = array_merge($createUserResponse['attributes'], ['password' => $randomPassword]);
        $this->storePelicanUserLocally($pelicanUserData);

        return $createUserResponse['attributes']['id'];
    }

    /**
     * Store the Pelican user data locally for future reference
     *
     * @return void
     */
    private function storePelicanUserLocally(array $pelicanUserData): void
    {
        $this->order->createExternalUser([
            'external_id' => $pelicanUserData['id'],
            'username' => $pelicanUserData['username'],
            'password' => $pelicanUserData['password'] ?? 'unknown',
            'data' => $pelicanUserData,
         ]);
    }

    /**
     * Email the user their Pelican panel credentials
     *
     * @return void
     */
    private function emailPterodactylUserCredentials(string $email, string $password): void
    {
        $this->order->user->email([
            'subject' => 'Game Panel Account Created',
            'content' => "Your account has been created on the game panel. You can login using the following details: <br><br> Email: {$email} <br> Password: {$password}",
            'button' => [
                'name' => 'Login to Game Panel',
                'url' => settings('pelican::hostname'),
            ]
        ]);
    }

    /**
     * This function is responsible for upgrading or downgrading
     * an instance of this service. This method is optional
     * If your service doesn't support upgrading, remove this method.
     *
     * Optional
     * @return void
     */
    public function upgrade(Package $oldPackage, Package $newPackage)
    {

    }

    /**
     * This function is responsible for suspending an instance of the
     * service. This method is called when a order is expired or
     * suspended by an admin
     *
     * @return void
     */
    public function suspend(array $data = [])
    {

    }

    /**
     * This function is responsible for unsuspending an instance of the
     * service. This method is called when a order is activated or
     * unsuspended by an admin
     *
     * @return void
     */
    public function unsuspend(array $data = [])
    {

    }

    /**
     * This function is responsible for deleting an instance of the
     * service. This can be anything such as a server, vps or any other instance.
     *
     * @return void
     */
    public function terminate(array $data = [])
    {

    }
}