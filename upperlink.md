Endpoint
https://client.upperlink.ng/clients/modules/addons/DomainsReseller/api/index.php
Authorization
Username
This is an email address of the reseller's client registered in your WHMCS.
Token
Token is an API Key transformed into SHA256 hash using the reseller's email address and the current time encoded with base64.

base64_encode(hash_hmac("sha256", "<api-key>", "<email>:<gmdate("y-m-d H")>)"))
Example
$endpoint   = "https://client.upperlink.ng/clients/modules/addons/DomainsReseller/api/index.php";
$action     = "/order/domains/renew";
$params     = [
    "domain"    => "example.com",
    "regperiod" => "3",
    "addons"    => [
        "dnsmanagement"     => 0,
        "emailforwarding"   => 1,
        "idprotection"      => 1,
    ]
];
$headers = [
    "username: email@example.com",
    "token: ". base64_encode(hash_hmac("sha256", "1234567890QWERTYUIOPASDFGHJKLZXCVBNM", "email@example.com:".gmdate("y-m-d H")))
];

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, "{$endpoint}{$action}");
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($curl);
curl_close($curl);


Calls: POST
 - 
/order/domains/register
 - 
Register Domain
domain
Type: text
 
Validators: required, text
regperiod
Type: numeric
 
Validators: required, numeric
domainfields
Type: text
 
Validators: text
addons
Type: addons
 
nameservers
Type: nameservers
 
Validators: required
contacts
Type: contacts
 
Validators: required
idnLanguage
Type: text
 
Validators: text
POST
 - 
/order/domains/transfer
 - 
Transfer Domain
domain
Type: text
 
Validators: required, text
eppcode
Type: text
 
Validators: text
regperiod
Type: numeric
 
Validators: required, numeric
domainfields
Type: text
 
Validators: text
addons
Type: addons
 
nameservers
Type: nameservers
 
Validators: required
contacts
Type: contacts
 
Validators: required
idnLanguage
Type: text
 
Validators: text
POST
 - 
/order/domains/renew
 - 
Renew Domain
domain
Type: text
 
Validators: required, text
regperiod
Type: numeric
 
Validators: required, numeric
addons
Type: addons
 
idnLanguage
Type: text
 
Validators: text
POST
 - 
/domains/{domain}/release
 - 
Release Domain
domain
Type: text
 
Validators: required, text
transfertag
Type: text
 
Validators: required, text
GET
 - 
/domains/{domain}/eppcode
 - 
Get EPP Code
domain
Type: text
 
Validators: required, text
GET
 - 
/domains/{domain}/contact
 - 
Get Contact Details
domain
Type: text
 
Validators: required, text
POST
 - 
/domains/{domain}/contact
 - 
Save Contact Details
domain
Type: text
 
Validators: required, text
contactdetails
Type: contactdetails
 
Validators: required
GET
 - 
/domains/{domain}/lock
 - 
Get Registrar Lock
domain
Type: text
 
Validators: required, text
POST
 - 
/domains/{domain}/lock
 - 
Save Registrar Lock
domain
Type: text
 
Validators: required, text
lockstatus
Type: text
 
Validators: required, text
GET
 - 
/domains/{domain}/dns
 - 
Get DNS
domain
Type: text
 
Validators: required, text
POST
 - 
/domains/{domain}/dns
 - 
Save DNS
domain
Type: text
 
Validators: required, text
dnsrecords
Type: dnsrecords
 
Validators: required
POST
 - 
/domains/{domain}/delete
 - 
Request Deletion
domain
Type: text
 
Validators: required, text
POST
 - 
/domains/{domain}/transfersync
 - 
Transfer Sync
domain
Type: text
 
Validators: required, text
POST
 - 
/domains/{domain}/sync
 - 
Domain Sync
domain
Type: text
 
Validators: required, text
GET
 - 
/domains/{domain}/email
 - 
Get Email Forwarding
domain
Type: text
 
Validators: required, text
POST
 - 
/domains/{domain}/email
 - 
Save Email Forwarding
domain
Type: text
 
Validators: required, text
prefix
Type: array
 
forwardto
Type: array
 
POST
 - 
/domains/{domain}/protectid
 - 
ID Protect Toggle
domain
Type: text
 
Validators: required, text
status
Type: int
 
Validators: required, numeric
POST
 - 
/domains/lookup
 - 
Check Availability
searchTerm
Type: text
 
Validators: text
punyCodeSearchTerm
Type: text
 
Validators: text
tldsToInclude
Type: array
 
Validators: isArray
isIdnDomain
Type: boolean
 
premiumEnabled
Type: boolean
 
POST
 - 
/domains/lookup/suggestions
 - 
Get Domain Suggestions
searchTerm
Type: text
 
Validators: text
punyCodeSearchTerm
Type: text
 
Validators: text
tldsToInclude
Type: array
 
Validators: isArray
isIdnDomain
Type: boolean
 
premiumEnabled
Type: boolean
 
suggestionSettings
Type: array
 
Validators: isArray
GET
 - 
/domains/{domain}/nameservers
 - 
Get Nameservers
domain
Validators: required, text
POST
 - 
/domains/{domain}/nameservers
 - 
Save Nameservers
domain
Validators: required, text
ns1
Validators: required, text
ns2
Validators: required, text
ns3
Validators: text
ns4
Validators: text
ns5
Validators: text
POST
 - 
/domains/{domain}/nameservers/register
 - 
Register Nameserver
domain
Validators: required, text
nameserver
Validators: required, text
ipaddress
Validators: required, text
POST
 - 
/domains/{domain}/nameservers/modify
 - 
Modify Nameserver
nameserver
Validators: required, text
currentipaddress
Validators: required, text
newipaddress
Validators: required, text
POST
 - 
/domains/{domain}/nameservers/delete
 - 
Delete Nameserver
nameserver
Validators: required, text
GET
 - 
/order/pricing/domains/{type}
 - 
Cart Get Pricing Register
domain
Type: text
 
Validators: required, text
GET
 - 
/order/pricing/domains/{type}
 - 
Cart Get Pricing Renew
domain
Type: text
 
Validators: required, text
GET
 - 
/order/pricing/domains/{type}
 - 
Cart Get Pricing Transfer
domain
Type: text
 
Validators: required, text
GET
 - 
/billing/credits
 - 
Get Credits
GET
 - 
/version
 - 
Get Version
GET
 - 
/tlds
 - 
Get Available TLDs
GET
 - 
/tlds/pricing
 - 
Get TLDs Pricing
GET
 - 
/domains/{domain}/information
 - 
Get Domain Information
domain
Type: text
 
Validators: required, text

Models: Nameservers
ns1
Type: text
 
Validators: required, text
ns2
Type: text
 
Validators: required, text
ns3
Type: text
 
Validators: text
ns4
Type: text
 
Validators: text
ns5
Type: text
 
Validators: text
DNS Records
hostname
Type: text
 
Validators: required, text
type
Type: text
 
Validators: required, text
address
Type: text
 
Validators: required, text
priority
Type: numeric
 
Validators: required
recid
Type: text
 
Validators: requires, text
Contacts
registrant
Type: contact
 
tech
Type: contact
 
billing
Type: contact
 
admin
Type: contact
 
Contact Details
Registrant
Type: contact
 
Validators: required
Technical
Type: contact
 
Validators: required
Billing
Type: contact
 
Validators: required
Admin
Type: contact
 
Validators: required
Contact
firstname
Type: text
 
Validators: required, text
lastname
Type: text
 
Validators: required, text
fullname
Type: text
 
Validators: required, text
companyname
Type: text
 
Validators: required, text
email
Type: text
 
Validators: required, text
address1
Type: text
 
Validators: required, text
address2
Type: text
 
Validators: text
city
Type: text
 
Validators: required, text
state
Type: text
 
Validators: required, text
postcode
Type: text
 
Validators: required, text
country
Type: text
 
Validators: required, text
phonenumber
Type: text
 
Validators: required, text
Addons
dnsmanagement
Type: numeric
 
Validators: numeric
emailforwarding
Type: numeric
 
Validators: numeric
idprotection
Type: numeric
 
Validators: numeric