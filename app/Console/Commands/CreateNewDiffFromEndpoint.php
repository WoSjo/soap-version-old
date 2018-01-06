<?php

namespace SoapVersion\Console\Commands;

use Illuminate\Console\Command;
use SoapClient;
use SoapVersion\Models\Server\Endpoint;
use SoapVersion\Models\Version\Version;

class CreateNewDiffFromEndpoint extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:diff:endpoint {endpoint : The id of the endpoint}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the endpoint and create a diff.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $endpointId = $this->argument('endpoint');
        $endpoint = Endpoint::with('server.type')->find($endpointId);

        $this->info(sprintf('Running for diff for endpoint with id `%s`', $endpointId));

        if ($endpoint->server->type->getAttribute('name') !== 'soap') {
            $this->warn('Can only run a soap endpoint at the moment');
        }

        try {
            $options = array(
                'uri' => 'http://schemas.xmlsoap.org/soap/envelope/',
                'style' => SOAP_RPC,
                'use' => SOAP_ENCODED,
                'soap_version' => SOAP_1_1,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => 15,
                'trace' => true,
                'encoding' => 'UTF-8',
                'exceptions' => true,
            );

            $functionName = $endpoint->getAttribute('function');
            $functionData = $endpoint->getAttribute('data');

            list($key, $value) = explode(':', $functionData);

            $dataArray = [];
            $dataArray[$key] = $value;

            $soapClient = new SoapClient($endpoint->server->host, $options);
            $result = $soapClient->__soapCall($functionName, [
                $functionName => $dataArray
            ], null);

            $lastVersion = Version::byEndpoint($endpoint)->orderByDesc('created_at')->first();

            $version = $endpoint->versions()->create([
                'compare' => true,
                'endpoint_result' => serialize($result)
            ]);

            if ($lastVersion !== null) {
                $lastVersion->compareAbleVersion()->save($version);
            }

        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }

        $this->info('Finished creating diff.');
    }
}
