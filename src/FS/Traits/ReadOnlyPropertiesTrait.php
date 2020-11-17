<?php

namespace GAEDrive\FS\Traits;

trait ReadOnlyPropertiesTrait
{
    use ProtectedPropertiesTrait;

    /**
     * @param array $properties
     *
     * @return array
     */
    public function getProperties($properties)
    {
        return [
            '{DAV:}isreadonly'                                => '1',
            '{urn:schemas-microsoft-com:}Win32FileAttributes' => '00000001',
        ];
    }
}
