<?php
// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Forms;

use Icinga\Web\Form;
use Icinga\Web\Notification;
use ipl\Sql\Connection;

class CleanupDisappearedServersForm extends Form
{
    /**
     * @var Connection
     */
    protected $db;

    public function createElements(array $formData)
    {
        $this->setTitle($this->translate('Clean up disappeared servers not seen for the last...'));

        $this->addElements([
            [
                'number',
                'amount',
                [
                    'min'       => 1,
                    'value'     => 1,
                    'required'  => true
                ]
            ],
            [
                'select',
                'unit',
                [
                    'value'         => 'day',
                    'required'      => true,
                    'multiOptions'  => [
                        'minute'    => $this->translate('Minutes'),
                        'hour'      => $this->translate('Hours'),
                        'day'       => $this->translate('Days')
                    ]
                ]
            ]
        ]);

        $this->setSubmitLabel($this->translate('Clean up'));
    }

    public function onSuccess()
    {
        $diff = (int) $this->getValue('amount');

        switch ($this->getValue('unit')) {
            case 'minute':
                $diff *= 60;
                break;
            case 'hour':
                $diff *= 60 * 60;
                break;
            case 'day':
                $diff *= 60 * 60 * 24;
                break;
        }

        Notification::success(sprintf(
            $this->translate('Cleaned up %d disappeared servers!'),
            $this->db->delete('x509_target', ['latest_certificate_chain_id IS NULL AND last_seen < ?' => time() - $diff])->rowCount()
        ));

        return true;
    }

    /**
     * Set {@link db}
     *
     * @param Connection $db
     *
     * @return $this
     */
    public function setDb($db)
    {
        $this->db = $db;

        return $this;
    }
}
