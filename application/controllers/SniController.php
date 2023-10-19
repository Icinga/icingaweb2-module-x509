<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Controllers;

use Icinga\Exception\NotFoundError;
use Icinga\Module\X509\Common\Database;
use Icinga\Module\X509\Common\JobUtils;
use Icinga\Module\X509\Common\Links;
use Icinga\Module\X509\Forms\Config\SniConfigForm;
use Icinga\Module\X509\Job;
use Icinga\Module\X509\Model\X509Job;
use Icinga\Module\X509\SniIniRepository;
use Icinga\Web\Controller;
use Icinga\Web\Url;
use ipl\Stdlib\Filter;

class SniController extends Controller
{
    use Database;
    use JobUtils;
    private $job;
    public function init()
    {
        parent::init();
        $jobId = $this->getParam("jobid");
        if( $jobId != null){
            $this->job = X509Job::on($this->getDb())
                ->filter(Filter::equal('id', $jobId))
                ->first();
        }

    }

    public function getTabs()
    {
        $tabs = parent::getTabs();
        if( $this->job != null){
            $tabs
                ->add('job-activities', [
                    'label' => $this->translate('Job Activities'),
                    'url'   => Links::job($this->job)
                ])
                ->add('schedules', [
                    'label' => $this->translate('Schedules'),
                    'url'   => Links::schedules($this->job)
                ])->add('sni', [
                    'label' => $this->translate('SNI'),
                    'url'   => Links::sni($this->job)
                ]);
            return $tabs->activate('sni');;
        }else{
            return $this->Module()->getConfigTabs()->activate('sni');
        }

    }

    /**
     * List all maps
     */
    public function indexAction()
    {
        $this->view->tabs = $this->getTabs();
        $repo = new SniIniRepository();
        $snis = $repo->select(array('ip'))->fetchAll();


        if($this->job != null){
            $cidrs = $this->parseCIDRs($this->job->cidrs);
            foreach ($snis as $index=>$sni){
                $isRelated=false;
                foreach ($cidrs as $cidr){
                    $subnet = $cidr[0];
                    $mask = $cidr[1];
                    $isRelated = $isRelated || Job::isAddrInside(Job::addrToNumber($sni->ip),$subnet,$mask);

                }
                if(! $isRelated){
                    unset($snis[$index]);
                }
            }
        }

        $this->view->sni = $snis;
    }

    /**
     * Create a map
     */
    public function newAction()
    {
        $form = $this->prepareForm()->add();

        $form->handleRequest();

        $this->renderForm($form, $this->translate('New SNI Map'));
    }

    /**
     * Update a map
     */
    public function updateAction()
    {
        $form = $this->prepareForm()->edit($this->params->getRequired('ip'));

        try {
            $form->handleRequest();
        } catch (NotFoundError $_) {
            $this->httpNotFound($this->translate('IP not found'));
        }

        $this->renderForm($form, $this->translate('Update SNI Map'));
    }

    /**
     * Remove a map
     */
    public function removeAction()
    {
        $form = $this->prepareForm()->remove($this->params->getRequired('ip'));

        try {
            $form->handleRequest();
        } catch (NotFoundError $_) {
            $this->httpNotFound($this->translate('IP not found'));
        }

        $this->renderForm($form, $this->translate('Remove SNI Map'));
    }

    /**
     * Assert config permission and return a prepared RepositoryForm
     *
     * @return  SniConfigForm
     */
    protected function prepareForm()
    {
        $this->assertPermission('config/x509');

        return (new SniConfigForm())
            ->setRepository(new SniIniRepository())
            ->setRedirectUrl(Url::fromPath('x509/sni'));
    }
}
