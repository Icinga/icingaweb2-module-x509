<?php

namespace ipl\Translation;

/**
 * Representation of translators
 */
interface TranslatorInterface
{
    /**
     * Translate a message
     *
     * @param   string  $message
     *
     * @return  string
     */
    public function translate($message);

    /**
     * Translate a plural message
     *
     * @param   string  $singular
     * @param   string  $plural
     * @param   int     $number
     *
     * @return  string
     */
    public function translatePlural($singular, $plural, $number);
}
