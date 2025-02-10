<?php

namespace App\Services\Pelican;

use App\Services\ServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
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
                "key" => "location_id",
                "name" => "Location ID",
                'col' => 'col-12',
                "description" =>  "The location on which the server should be deployed.",
                "type" => "text",
                "rules" => ['required', 'string', Rule::in(array_keys(config('pelican.locations', [])))],
                'is_configurable' => true,
            ],
            [
                "key" => "egg_id",
                "name" => "Egg ID",
                'col' => 'col-12',
                "description" =>  "Egg ID of the server you want to use for this package. You can find the egg ID by going to the egg page and looking at the URL. It will be the number at the end of the URL.",
                "type" => "text",
                'save_on_change' => true,
                "rules" => ['required', 'numeric'],
                'is_configurable' => false,
            ],
        ];

        try {
            // if egg id is not set return the default config
            if(!$package->data('egg_id')) {
                return $config;
            }

            $egg = Service::makeRequest('/api/application/eggs/' . $package->data('egg_id', 3), 'get', ['include' => 'variables']);

            $config = array_merge($config, [
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
                "key" => "allocation_limit",
                "name" => "Allocation Limit",
                "description" => "The total number of allocations a user is allowed to create for this server on Pterodactyl Panel.",
                "type" => "number",
                "min" => 0,
                "rules" => ['required'], // laravel validation rules
                'is_configurable' => true,
            ],
            [
                "col" => "col-4",
                "key" => "backup_limit",
                "name" => "Backup Limit",
                "description" => "The total number of backups a user is allowed to create for this server on Pterodactyl Panel.",
                "type" => "number",
                "min" => 0,
                "rules" => ['required'], // laravel validation rules
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
                'is_configurable' => false,
            ],
            [
                "col" => "col-4",
                "key" => "swap_limit",
                "name" => __('admin.swap'),
                "description" => __('admin.swap_desc'),
                "type" => "number",
                "default_value" => 0,
                "rules" => ['required'],
                'is_configurable' => false,
            ],
            [
                "col" => "col-4",
                "key" => "block_io_weight",
                "name" => __('admin.block_io_weight'),
                "description" =>  __('admin.block_io_weight_desc'),
                "type" => "number",
                "default_value" => 500,
                "rules" => ['required'],
                'is_configurable' => false,
            ]]);

            $config[] = [
                "col" => "col-4",
                "key" => "docker_image",
                "name" => "Docker Image",
                "description" => "Docker image to use for this server",
                "type" => "text",
                "default_value" => $egg['attributes']['docker_image'],
                "rules" => ['required'],
            ];

            $config[] = [
                "col" => "col-4",
                "key" => "startup",
                "name" => "Startup Command",
                "description" => "Startup command for this server",
                "type" => "text",
                "default_value" => $egg['attributes']['startup'],
                "rules" => ['required'],
                'is_configurable' => false,
            ];

            foreach($egg['attributes']['relationships']['variables']['data'] as $variable) {
                $variable = $variable['attributes'];
                
                // check if rules is an string, if so convert it to array
                if(is_string($variable['rules'])) {
                    $variable['rules'] = explode('|', $variable['rules']);
                }
                
                $config[] = [
                    "col" => "col-4",
                    "key" => "environment[{$variable['env_variable']}]",
                    "name" => $variable['name'],
                    "description" => $variable['description'],
                    "type" => "text",
                    "default_value" => $variable['default_value'] ?? '',
                    "rules" => $variable['rules'],
                    'is_configurable' => false,
                ];
            }


        } catch(\Exception $e) {
            // if we reach here, the egg id is invalid or the egg does not exist
            // return the default config
            return $config;
        }

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
    * This function is called right before the user makes the payment
    * We can use it to check if there are allocations available
    *
     * @throw Exception
    */
    public static function eventCheckout(Package $package)
    {
        try {
            // get location id from the package data
            $locationId = $package->data('location_id');
            
            if(!$locationId) {
                throw new \Exception('Location ID has not been configured for this package');
            }

            // if location id is set as a custom option, we override the location id
            if(isset(request()->get('custom_option')['location_id'])) {
                $locationId = request()->get('custom_option')['location_id'];

                // check if the location exists in the config
                if(!array_key_exists($locationId, config('pelican.locations', []))) {
                    throw new \Exception('Invalid location ID');
                }
            }

            $allowedNodes = config('pelican.locations.' . request()->get('custom_option')['location_id'] . '.nodes', []);

            // check if allowed nodes are set and are not empty
            if(empty($allowedNodes)) {
                throw new \Exception('No nodes are available for this location');
            }

            Service::findViableNode(
                allowedNodes: $allowedNodes,
                diskLimit: $package->data('disk_limit', 0),
                memoryLimit: $package->data('memory_limit', 0),
                cpuLimit: $package->data('cpu_limit', 0),
            );
        } catch(\Exception $e) {
            return ['error' => $e->getMessage()];
        }
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

        if (!in_array($method, ['get', 'post', 'put', 'delete', 'patch'])) {
            throw new \Exception('Invalid method');
        }

        $response = Http::withToken(settings('encrypted::pelican::api_key'))
            ->$method(settings('pelican::hostname') . $endpoint, $data);

        if ($response->failed() OR $response->json() === null) {
            // dd($response, $response->json(), $endpoint, $data, $method);
            throw new \Exception("Failed to connect to Pelican API at endpoint: $endpoint with status code: {$response->status()} and response: {$response->body()}");
        }

        return $response;
    }

    /**
     * Change the Pelican password
     */
    public function changePassword(Order $order, string $newPassword)
    {
        try {
            $pelicanUser = $order->getExternalUser()->data;

            $response = Service::makeRequest("/api/application/users/{$pelicanUser['id']}", 'patch', [
                'email' => $pelicanUser['email'],
                'username' => $pelicanUser['username'],
                'password' => $newPassword,
            ]);

            $order->updateExternalPassword($newPassword);
        } catch (\Exception $error) {
            return redirect()->back()->withError("Something went wrong, please try again.");
        }

        return redirect()->back()->withSuccess("Password has been changed");
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
        $order = $this->order;
        $package = $order->package;

        $locationId = $order->option('location_id');

        $nodes = config('pelican.locations.' . $locationId . '.nodes', []);

        $node = Service::findViableNode(
            allowedNodes: $nodes,
            diskLimit: $order->option('disk_limit', 0),
            memoryLimit: $order->option('memory_limit', 0),
            cpuLimit: $order->option('cpu_limit', 0),
        );

        // Create the server on Pelican panel
        $createServerResponse = Service::makeRequest("/api/application/servers", 'post', [
            'external_id' => "wemx{$order->id}",
            'name' => $package->name,
            'user' => $pelicanUserId,
            'egg' => $package->data('egg_id'),
            'startup' => $package->data('startup'),
            'docker_image' => $package->data('docker_image'),
            'environment' => $package->data('environment', []),
            "limits" => [
                "memory" => $order->option('memory_limit', 0),
                "swap" => $order->option('swap_limit', 0),
                "disk" => $order->option('disk_limit', 0),
                "io" => $order->option('block_io_weight', 500),
                "cpu" => $order->option('cpu_limit', 0),
            ],
            "feature_limits" => [
                "databases" => $order->option('database_limit', 0),
                "allocations" => $order->option('allocation_limit', 0),
                'backups' => $order->option('backup_limit', 0),
            ],
            'allocation' => [
                'default' => $node['allocation_id'],
            ],
            "start_on_completion" => true,
            "skip_scripts" => false,
            "oom_disabled" => false,
            "swap_disabled" => false,
        ]);

        // check if the server was created successfully
        if(!isset($createServerResponse['attributes'])) {
            throw new \Exception('Failed to create server on Pelican panel');
        }

        $server = $createServerResponse['attributes'];

        // store the server data locally
        $order->update([
            'external_id' => $server['id'],
            'data' => $server,
        ]);
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
            // Attempt to find the user on Pelican with the same email
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
        $this->emailPelicanCredentials(
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
    private function emailPelicanCredentials(string $email, string $password): void
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
     * Find a viable node based on the order requirements
     * 
     * Returns the node id and allocation id
     *
     * @return array
     */
    private static function findViableNode(array $allowedNodes = [], string|int $diskLimit = 0, string|int $memoryLimit = 0, string|int $cpuLimit = 0): array
    {
        $findDeployableNodes = Service::makeRequest('/api/application/nodes/deployable', 'get', [
            'disk' => $diskLimit,
            'memory' => $memoryLimit,
            'cpu' => $cpuLimit,
            'include' => 'allocations',
        ]);

        if(!isset($findDeployableNodes['data']) OR empty($findDeployableNodes['data'])) {
            throw new \Exception('Could not find node satisfying the requirements');
        }

        $nodes = $findDeployableNodes['data'];

        foreach($nodes as $node) {
            $node = $node['attributes'];
            
            // if node is not in allowed nodes, skip
            if(!empty($allowedNodes) AND !in_array($node['id'], $allowedNodes)) {
                continue;
            }

           // now that we have determined the node, lets find an allocation
           $allocations = $node['relationships']['allocations']['data'];

           // lets go over each allocation and ensure its not in use
           foreach($allocations as $allocation) {
               $allocation = $allocation['attributes'];

               // check if the allocation is in use
               if($allocation['assigned']) {
                   continue;
               }

               // allocation is not in use, return the node id and allocation id
               return [
                   'node_id' => $node['id'],
                   'allocation_id' => $allocation['id'],
               ];
           }

           // if we reach here, no allocation was found
           // in the future, add logic to create a new allocation
           // on one of the available nodes


           // for now, throw an exception
           throw new \Exception('Could not find a free allocation on the node, please contact support');
        }

        // theoretically, we should never reach here but we assume no node was found
        throw new \Exception('Could not find a node satisfying the requirements');
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
        Service::makeRequest("/api/application/servers/{$this->order->external_id}/suspend", 'post');
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
        Service::makeRequest("/api/application/servers/{$this->order->external_id}/unsuspend", 'post');
    }

    /**
     * This function is responsible for deleting an instance of the
     * service. This can be anything such as a server, vps or any other instance.
     *
     * @return void
     */
    public function terminate(array $data = [])
    {
        Service::makeRequest("/api/application/servers/{$this->order->external_id}", 'delete');
    }
}
