# Mautic 3

This has now been included in Mautic 3 and thus should only be used for Mautic 2 compatible plugins. Everything is the same except the namespace is `\Mautic\IntegrationsBundle`

# Mautic Integration

> Integrations solutions structured to mirror current Integrations and created as transition to final product.

## Install integrations bundle

Refer to https://github.com/mautic-inc/plugin-integrations/wiki for instructions on how to install and use this plugin.

## Features

### Tokens
#### Integration Object Token
This bundle makes the Integration Object Token available. This token can be used to insert data relating to objects which have been synced to Mautic from its various integrations.
The token will create an HTML link to the object's location in the integration. For instance, when used with contacts that have been imported from Salesforce, the link will take you to the corresponding contact in Salesforce.

The format for this token is as follows: `{mapped-integration-object=Lead | integration=Salesforce2 | default=Not Found | link-text=Go to SFDC | base-url=$baseUrl}` 

 - `mapped-integration`: This is the name of the object being mapped. i.e 'Lead' or 'Contact' for Salesforce. It corresponds to the value in the `integration_object_name` column.
 - `integration`: The name of the integration. i.e 'Salesforce2'. It corresponds to the value in the `integration` column.
 - `default`: Some default text to show if the value isn't found
 - `link-text`: The text to use in for the link in the <a> tag that is generated. i.e <a href='#'>Your Link Text</a>
 - `base-url`: The base url of the integration. i.e for Salesforce, something like https://yourdomain.salesforce.com. The path to the object will be appended to this.

### Sync command

`$ app/console mautic:integrations:sync Magento --first-time-sync --start-datetime="2019-09-12T12:00:00"`

This is how you should use it when you configure an integration (Magento in this case) and run the sync for the first time. Specify also from what date it should look for the entities to sync. This way you can controll how big batch of records you will sync with one command. If you want to sync with multiple chunks by date ranges, `--end-datetime` option will be helpful too.

The sync command in basic use looks like this:

`$ app/console mautic:integrations:sync Magento`

It will sync all new records from and to Mautic for Magento. There is no need to specify the date range as Mautic is smart enough to read the start date from the records it has already synchronized. And the end date is "now".

`$ app/console mautic:integrations:sync Magento --disable-pull --mautic-object-id=contact:12 --mautic-object-id=contact:13`

There is also option to force sync of specific objects. With the `--disable-pull` flag the sync will skip the pull process. If some `--mautic-object-id` options are set it will not sync by a date range but rather only the IDs you will specify. `--disable-push` only disables the push. Pulling specific records by ID is not implemented yet.

The format of the `--mautic-object-id` values is `object type[colon]object ID`. Mautic can sync 2 object types: `contact` and `company`. The latter is not implemented yet.

The `--integration-object-id` uses the same format as `--mautic-object-id` but it's up to each integration to support it.

Similarly, you can push specific Mautic contacts to the integration you are developing like the following example. It can be useful if you want to push as a campaign/form/point action.

```php
$mauticObjectIds = new \MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\ObjectIdsDAO();
$mauticObjectIds->addObjectId('contact', '12');
$mauticObjectIds->addObjectId('contact', '13');

$inputOptions = new MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\InputOptionsDAO(
    [
        'integration'      => 'Magento',
        'disable-pull'     => true,
        'mautic-object-id' => $mauticObjectIds,
    ]
);

/** @var \MauticPlugin\IntegrationsBundle\Sync\SyncService\SyncServiceInterface $syncService **/
$syncService->processIntegrationSync($inputOptions);
```

## Tests

This plugin is covered with some unit tests, functional tests, static analysis and code style check that run also in CI on every push.

### Useful commands

Always run following commands from the `plugins/IntegrationsBundle` directory.

#### `$ composer test`

With this command you can run all the tests for this plugin. Functional tests included.

#### `$ composer quicktest`

With this command you can run all the tests for this plugin except functional tests which makes it fast.

#### `$ composer phpunit -- --filter x`

With this command you can filter which tests you want to run. Replace `x` with whatever test class name or method you focus on.

#### `$ composer fixcs`

If you wan to automatically fix code styles then run this.