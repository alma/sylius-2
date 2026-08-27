<?php

declare(strict_types=1);

namespace Alma\Sylius\Eligibility;

use Alma\Client\Application\DTO\AddressDto;
use Sylius\Component\Addressing\Model\AddressInterface;

final class SyliusAddressMapper
{
    public function toAlmaAddress(?AddressInterface $address, ?string $email = null): ?AddressDto
    {
        if ($address === null) {
            return null;
        }

        $dto = new AddressDto();
        if (($firstName = $address->getFirstName()) !== null && $firstName !== '') {
            $dto->setFirstName($firstName);
        }
        if (($lastName = $address->getLastName()) !== null && $lastName !== '') {
            $dto->setLastName($lastName);
        }
        if (($street = $address->getStreet()) !== null && $street !== '') {
            $dto->setLine1($street);
        }
        if (($postcode = $address->getPostcode()) !== null && $postcode !== '') {
            $dto->setPostalCode($postcode);
        }
        if (($city = $address->getCity()) !== null && $city !== '') {
            $dto->setCity($city);
        }
        if (($country = $address->getCountryCode()) !== null && $country !== '') {
            $dto->setCountry($country);
        }
        if (($phone = $address->getPhoneNumber()) !== null && $phone !== '') {
            $dto->setPhone($phone);
        }
        if (($company = $address->getCompany()) !== null && $company !== '') {
            $dto->setCompany($company);
        }
        if (($province = $address->getProvinceName()) !== null && $province !== '') {
            $dto->setStateProvince($province);
        }
        if ($email !== null && $email !== '') {
            $dto->setEmail($email);
        }

        return $dto;
    }
}
