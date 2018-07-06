<?php

namespace Icinga\Module\Vspheredb\Controllers;

use Icinga\Module\Vspheredb\DbObject\Datastore;
use Icinga\Module\Vspheredb\PathLookup;
use Icinga\Module\Vspheredb\Web\Controller;
use Icinga\Module\Vspheredb\Web\Table\VMotionHistoryTable;
use Icinga\Module\Vspheredb\Web\Table\VmsOnDatastoreTable;
use Icinga\Module\Vspheredb\Web\Widget\DatastoreUsage;
use Icinga\Module\Vspheredb\Web\Widget\OverallStatusRenderer;
use Icinga\Util\Format;
use dipl\Html\Html;
use dipl\Html\Link;
use dipl\Web\Widget\NameValueTable;

class DatastoreController extends Controller
{
    /**
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function indexAction()
    {
        $uuid = hex2bin($this->params->getRequired('uuid'));
        $ds = $this->addDatastore();

        $lookup = new PathLookup($this->db());
        $path = Html::tag('span', ['class' => 'dc-path'])->setSeparator(' > ');
        foreach ($lookup->getObjectNames($lookup->listPathTo($uuid, false)) as $parentUuid => $name) {
            $path->add(Link::create(
                $name,
                'vspheredb/datastores',
                ['uuid' => bin2hex($parentUuid)],
                ['data-base-target' => '_main']
            ));
        }

        $usage = (new DatastoreUsage($ds))->loadAllVmDisks()->addFreeDatastoreSpace();
        $table = new NameValueTable();
        $statusRenderer = new OverallStatusRenderer();
        $table->addNameValuePairs([
            $this->translate('Status') => $statusRenderer($ds->object()->get('overall_status')),
            $this->translate('Path') => $path,
            $this->translate('Capacity') => $this->bytes($ds->get('capacity')),
            $this->translate('Free') => $this->bytes($ds->get('free_space')),
            $this->translate('Used') => $this->bytes(
                $ds->get('capacity') - $ds->get('free_space')
            ),
            $this->translate('Uncommitted') => $this->bytes($ds->get('uncommitted')),
            $this->translate('Sizing') => $this->sizingInfo($ds),
        ]);
        $vms = VmsOnDatastoreTable::create($ds);

        $this->content()->add([$table, $usage, $vms]);
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function vmotionsAction()
    {
        $ds = $this->addDatastore();
        $table = new VMotionHistoryTable($this->db());
        $table->filterDatastore($ds)
            ->renderTo($this);
    }

    /**
     * @return Datastore
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function addDatastore()
    {
        $ds = Datastore::load(hex2bin($this->params->getRequired('uuid')), $this->db());
        $this->addTitle($ds->object()->get('object_name'));
        $this->handleTabs();

        return $ds;
    }

    /**
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     * @throws \Icinga\Exception\ProgrammingError
     */
    protected function handleTabs()
    {
        $params = ['uuid' => $this->params->get('uuid')];
        $this->tabs()->add('index', [
            'label'     => $this->translate('Datastore'),
            'url'       => 'vspheredb/datastore',
            'urlParams' => $params
        ])->add('vmotions', [
            'label'     => $this->translate('VMotions'),
            'url'       => 'vspheredb/datastore/vmotions',
            'urlParams' => $params
        ])->activate($this->getRequest()->getActionName());
    }

    protected function sizingInfo(Datastore $ds)
    {
    }

    protected function bytes($bytes)
    {
        return Format::bytes($bytes, Format::STANDARD_IEC);
    }
}
