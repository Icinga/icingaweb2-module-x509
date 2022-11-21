<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Controllers;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\X509\CertificatesTable;
use Icinga\Module\X509\Controller;
use Icinga\Module\X509\Model\X509Certificate;
use Icinga\Module\X509\Web\Control\SearchBar\ObjectSuggestions;
use ipl\Orm\Query;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\SortControl;

class CertificatesController extends Controller
{
    public function indexAction()
    {
        $this->getTabs()->enableDataExports();
        $this->addTitleTab($this->translate('Certificates'));

        try {
            $conn = $this->getDb();
        } catch (ConfigurationError $_) {
            $this->render('missing-resource', null, true);

            return;
        }

        $certificates = X509Certificate::on($conn);

        $sortColumns = [
            'subject'             => $this->translate('Certificate'),
            'issuer'              => $this->translate('Issuer'),
            'version'             => $this->translate('Version'),
            'self_signed'         => $this->translate('Is Self-Signed'),
            'ca'                  => $this->translate('Is Certificate Authority'),
            'trusted'             => $this->translate('Is Trusted'),
            'pubkey_algo'         => $this->translate('Public Key Algorithm'),
            'pubkey_bits'         => $this->translate('Public Key Strength'),
            'signature_algo'      => $this->translate('Signature Algorithm'),
            'signature_hash_algo' => $this->translate('Signature Hash Algorithm'),
            'valid_from'          => $this->translate('Valid From'),
            'valid_to'            => $this->translate('Valid To'),
            'duration'            => $this->translate('Duration'),
            'expires'             => $this->translate('Expiration')
        ];

        $limitControl = $this->createLimitControl();
        $paginator = $this->createPaginationControl($certificates);
        $sortControl = $this->createSortControl($certificates, $sortColumns);

        $searchBar = $this->createSearchBar($certificates, [
            $limitControl->getLimitParam(),
            $sortControl->getSortParam()
        ]);

        if ($searchBar->hasBeenSent() && ! $searchBar->isValid()) {
            if ($searchBar->hasBeenSubmitted()) {
                $filter = $this->getFilter();
            } else {
                $this->addControl($searchBar);
                $this->sendMultipartUpdate();
                return;
            }
        } else {
            $filter = $searchBar->getFilter();
        }

        $certificates->peekAhead($this->view->compact);

        $certificates->filter($filter);

        $this->addControl($paginator);
        $this->addControl($sortControl);
        $this->addControl($limitControl);
        $this->addControl($searchBar);

        // List of allowed columns to be exported
        $exportable = array_flip([
            'id', 'subject', 'issuer', 'version', 'self_signed', 'ca', 'trusted',
            'pubkey_algo', 'pubkey_bits', 'signature_algo', 'signature_hash_algo',
            'valid_from', 'valid_to'
        ]);

        $this->handleFormatRequest($certificates, function (Query $certificates) use ($exportable) {
            /** @var X509Certificate $cert */
            foreach ($certificates as $cert) {
                $cert['valid_from'] = (new \DateTime())
                    ->setTimestamp($cert['valid_from'])
                    ->format('l F jS, Y H:i:s e');
                $cert['valid_to'] = (new \DateTime())
                    ->setTimestamp($cert['valid_to'])
                    ->format('l F jS, Y H:i:s e');

                yield array_intersect_key(iterator_to_array($cert), $exportable);
            }
        });

        $this->addContent((new CertificatesTable())->setData($certificates));

        if (! $searchBar->hasBeenSubmitted() && $searchBar->hasBeenSent()) {
            $this->sendMultipartUpdate(); // Updates the browser search bar
        }
    }

    public function completeAction()
    {
        $suggestions = new ObjectSuggestions();
        $suggestions->setModel(X509Certificate::class);
        $suggestions->forRequest($this->getServerRequest());
        $this->getDocument()->add($suggestions);
    }

    public function searchEditorAction()
    {
        $editor = $this->createSearchEditor(X509Certificate::on($this->getDb()), [
            LimitControl::DEFAULT_LIMIT_PARAM,
            SortControl::DEFAULT_SORT_PARAM
        ]);

        $this->getDocument()->add($editor);
        $this->setTitle(t('Adjust Filter'));
    }
}
