<?php

declare(strict_types=1);

namespace BackendBase\Shared\ValueObject;

final class TaxIdentity
{
    public const TAXPAYER_PRIVATE   = 'private';
    public const TAXPAYER_CORPORATE = 'corporate';
    private string $taxIdNumber;
    private string $taxAdministrationOfficeName;
    private string $taxAdministrationOfficeCode;
    private string $taxPayerType;

    private function __construct(
        string $taxIdNumber,
        string $taxAdministrationOfficeName,
        string $taxAdministrationOfficeCode,
        string $taxPayerType
    ) {
        $this->taxIdNumber                 = $taxIdNumber;
        $this->taxAdministrationOfficeName = $taxAdministrationOfficeName;
        $this->taxAdministrationOfficeCode = $taxAdministrationOfficeCode;
        $this->taxPayerType                = $taxPayerType;
    }

    public static function fromTaxId(
        string $taxId,
        string $taxAdministrationOfficeName,
        string $taxAdministrationOfficeCode
    ) : self {
        $taxIdLength = strlen(trim($taxId));
        if ($taxIdLength === 11) {
            return self::fromPrivateCompany(new PrivateTaxId($taxId), $taxAdministrationOfficeName, $taxAdministrationOfficeCode);
        }
        if ($taxIdLength === 10) {
            return self::fromCorporateCompany(new CorporateTaxId($taxId), $taxAdministrationOfficeName, $taxAdministrationOfficeCode);
        }
    }

    public static function fromPrivateCompany(
        PrivateTaxId $taxId,
        string $taxAdministrationOfficeName,
        string $taxAdministrationOfficeCode
    ) : self {
        return new self(
            $taxId->taxId(),
            $taxAdministrationOfficeName,
            $taxAdministrationOfficeCode,
            self::TAXPAYER_PRIVATE
        );
    }

    public static function fromCorporateCompany(
        CorporateTaxId $taxId,
        string $taxAdministrationOfficeName,
        string $taxAdministrationOfficeCode
    ) : self {
        return new self(
            $taxId->taxId(),
            $taxAdministrationOfficeName,
            $taxAdministrationOfficeCode,
            self::TAXPAYER_CORPORATE
        );
    }

    public function taxIdNumber() : string
    {
        return $this->taxIdNumber;
    }

    public function taxAdministrationOfficeName() : string
    {
        return $this->taxAdministrationOfficeName;
    }

    public function taxAdministrationOfficeCode() : string
    {
        return $this->taxAdministrationOfficeCode;
    }

    public function taxPayerType() : string
    {
        return $this->taxPayerType;
    }
}
