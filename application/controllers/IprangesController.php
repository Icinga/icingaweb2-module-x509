<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509\Controllers;

use Icinga\Application\Config;
use Icinga\Module\X509\Forms\Config\Ipranges\AddForm;
use Icinga\Module\X509\Forms\Config\Ipranges\EditForm;
use Icinga\Module\X509\Forms\Config\Ipranges\RemoveForm;
use Icinga\Web\Controller;

class IprangesController extends Controller
{
    public function init()
    {
        $this->assertPermission('config/modules');
        parent::init();
    }

    public function indexAction()
    {
        $this->view->ranges = $this->getConfig()->toArray();
        uksort($this->view->ranges, [$this, 'usortCidr']);
        $this->view->tabs = $this->Module()->getConfigTabs()->activate('ipranges');
    }

    public function addAction()
    {
        $form = new AddForm();
        $form->setIprangesConfig($this->getConfig())
            ->setRedirectUrl('x509/ipranges/edit')
            ->handleRequest();

        $this->addTitleTab('add', $this->translate('Add range'), $this->translate('Add IP range'));
        $this->view->form = $form;
    }

    public function editAction()
    {
        $form = new EditForm();
        $form->setIprangesConfig($this->getConfig())
            ->setCurrentCidr($this->params->getRequired('cidr'))
            ->handleRequest();

        $this->addTitleTab('edit', $this->translate('Edit range'), $this->translate('Edit IP range'));
        $this->view->form = $form;
    }

    public function removeAction()
    {
        $form = new RemoveForm();
        $form->setIprangesConfig($this->getConfig())
            ->setCurrentCidr($this->params->getRequired('cidr'))
            ->setRedirectUrl('x509/ipranges')
            ->handleRequest();

        $this->addTitleTab('remove', $this->translate('Remove range'), $this->translate('Remove IP range'));
        $this->view->form = $form;
    }

    /**
     * Get the IP and port ranges "repository"
     *
     * @return Config
     */
    protected function getConfig()
    {
        return Config::module('x509', 'ipranges');
    }

    /**
     * Compare two CIDRs
     *
     * @param   string  $a
     * @param   string  $b
     *
     * @return  int         See {@link strcmp()}
     */
    protected function usortCidr($a, $b)
    {
        list($ap, $ab) = explode('/', $a, 2);
        list($bp, $bb) = explode('/', $b, 2);

        return strcmp(inet_pton($ap), inet_pton($bp)) ?: (int) $ab - (int) $bb;
    }

    /**
     * Add the probably only tab
     *
     * @param   string  $name
     * @param   string  $label
     * @param   string  $description
     */
    protected function addTitleTab($name, $label, $description)
    {
        $this->getTabs()->add($name, [
            'label'         => $label,
            'description'   => $description,
            'url'           => $this->getRequest()->getUrl(),
            'active'        => true
        ]);
    }
}
