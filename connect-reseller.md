API

DOCUMENTATION

Table of Contents
A - DOMAINS
Check Availability of Specified Domain
Bulk Domain Check for Multiple domains
Check Domain Suggestions List
Check TLD Suggestions List
Check Domain Price for Multiple Years
Get all TLD Prices.
Register
7.1. Special TLD’s requirements for domain registration:
7.2. IDN Supported Languages:
Transfer
Cancel Transfer
Validate a Transfer
Renew
Getting Details of the Domain using ID
Getting Details of the Domain using Domain Name
Search
Modify Nameserver of Domain
Modify Authcode of Domain
Manage Lock on Domain
Manage Privacy on Domain
Manage Domain Suspend
Manage Theft Protection on Domain
View Domain Secret Key
Manage DNS Management
Add DNS Record
Modify DNS Record
Delete DNS Record
View DNS Record
Modifying Domain Contact
To move domain from one client to another
Add SRV Record
Modify DNS Record for Domain
B - CONTACT
Add Contact
Modify Contact
View Contact
To get Registrant list of specific client
To Send RAA Verification mail
Send KYC Email to registrant.
C - CLIENT
Add Client
Modify Client
View Client
Change the Client Password
To Delete The Client
To Get A Client List
D - HOST
To Add Child Name Server
Modify Name Server IP
To Modify Host Child Name Server
To Delete Child Name Server
To Get Child Name Servers of a Domain
E - DOMAIN FORWARDING
To Set Domain Forwarding Details
To Get Domain Forwarding Details
To Update Domain Forwarding Details
To Delete Domain Forwarding Details
F – MISCELLANEOUS
Check Reseller Available funds.
A - DOMAINS
1. Check Availability of Specified Domain
Checks availability of the specified domain name and basis on domain type show their pricing.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/checkdomainavailable?APIKey=<Your_API_Ke
y>&websiteName=example.com
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
websiteName Domain Name that you need to check the
availability
Required String
Response:
responseMsg:
statusCode: If 200 then show domain pricing and 400 not available
message: Indicates the message
responseData:
domainType: Indicates domain type
available: check domain is available or not
registration fees: Indicates registration fee
renewalfees: Indicates renewal fee
transferFees: Indicates transfer fee
2. Bulk Domain Check for Multiple domains
Checks availability of the Multiple domain name and basis on domain type show their pricing.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/bulkDomainCheck?APIKey=<Your_API_Key>&
websiteNames=example1.com,example2.com
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
websiteNames Multiple Domain Names comma separated.
(Max. 200 domains per request)
Required String
Response:
responseMsg:
statusCode: If 200 then show domain pricing and 400 not available
message: Indicates the message
responseData:
websiteName: Domain Name
domainType: Indicates domain type
available: check domain is available or not
registration fees: Indicates registration fee
3. Check Domain Suggestions List
Checks suggested domains of the specified keyword.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/domainSuggestion?APIKey=<Your_API_Key>
&keyword=example.com&maxResult= 5
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
Keyword Domain Name keyword that you need to check
the suggestions for
Required String
maxResult No. Of suggestions to be displayed
(Max. Value = 50)
Required Int
Response:
responseMsg: {
"registryDomainSuggestionList": [
{
"domainName": "example.biz",
"price": 5.
},
{
"domainName": "example.co",
"price": 12.
},
{
"domainName": "Helloexample.com",
"price": 10.
},
{
"domainName": "Getexample.com",
"price": 10.
},
{
"domainName": "Teamexample.com",
"price": 10.
}
]
}
4. Check TLD Suggestions List
To get any keyword or domain suggestion with multiple TLD’s.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/getTldSuggestion?APIKey=<Your_API_Key>&
websiteName=example.com
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
WebsiteName Domain Name or keyword that you need to
get suggestions for
Required String
Response:
Users will get a response of 25 TLD’s using this API. The response will be as follows:
{
"responseMsg": {
"message": "Success",
"id": 0,
"reason": null,
"statusCode": 200
},
"responseData": [
{
"websiteName": "example.com",
"domainType": "Standard",
"available": true
},
{
"websiteName": "example.org",
"domainType": "-",
"available": false
},
]
}
5. Check Domain Price for Multiple Years
To check any domain price for multiple years.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/checkDomainPrice?APIKey=<Your_API_Key>&
websiteName=example.com
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
WebsiteName Domain Name that you need to register Required String
Response:
"responseMsg": {
"message": "Domain Available for Registration",
"id": 0,
"statusCode": 200
},
"responseData": {
"1": [
{
"description": "Registration Price for 1 Year is *****"
},
{
"description": "Registration Price for 2 Year is *****"
},
{
"description": "Registration Price for 3 Year is *****"
},
{
"description": "Registration Price for 4 Year is *****"
},
{
"description": "Registration Price for 5 Year is *****"
},
{
"description": "Registration Price for 6 Year is *****"
},
{
"description": "Registration Price for 7 Year is *****"
},
{
"description": "Registration Price for 8 Year is *****"
},
{
"description": "Registration Price for 9 Year is *****"

},
{
"description": "Registration Price for 10 Year is *****"
}
]
}
}
6. Get all TLD Prices.
To get all TLD prices.
Method: GET
URL: https://api.connectreseller.com/ConnectReseller/ESHOP/tldsync/?APIKey=<Your_API_Key>
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
Response:
"responseMsg":
{
"tld": "TLD Name",
"minPeriod": Minimum Year,
"maxPeriod": Maximum Year,
"registrationPrice": Registration Price,
"renewalPrice": Renewal Price,
"transferPrice": Transfer Price,
"currencyCode": "Currency"
}
7. Register
Registers the domain name.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/domainorder?APIKey=<YOUR_API_KEY>&Prod
uctType=1&Websitename=domainexample.com&Duration=1&IsWhoisProtection=true&ns1=nameserve
r1&ns2=nameserver2&ns3=nameserver3&ns4=nameserver4&Id=00&isEnablePremium=0&lang=xxx
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
ProductType Value:1 by default Required Integer
WebsiteName Domain Name that you need to register Required String
Duration Number of years for which you wish to
register this domain name
Required Integer
IsWhoisProtection Adds the privacy protection service for the
domain name
Required Boolean
ns1 The Name Servers 1 of the domain name Required String
ns 2 The Name Servers 2 of the domain name Required String
ns 3 The Name Servers 3 of the domain name Optional String
ns 4 The Name Servers 4 of the domain name Optional String
Id The customer ID for whom you wish to
register the domain name
Required Integer
isEnablePremium Value: 1 indicates registration of premium
domain
Required Integer
lang Language code of IDN domains to mentioned
as per section 6.
Optional String
Response:
responseMsg:
statusCode: If 200 then domain is register and other than 200 if domain is not register
message: Indicates the message and reason if domain is not register.
responseData: Null if domain is not register
creationDate: Indicates creation date of registered domain
expiryDate: Indicates expiry date of registered domain
msg: Indicates registry command message
msgCode: Indicates registry command code.
name: Domain name registered.
7.1. Special TLD’s requirements for domain registration:
To Register Below TLD Extra Parameters are required which are as follows:
TLD
Name

Required
Parameter
Description Required Type
.us
isUs Value should be 1 to successfully register .us domains. Required Boolean
appPurpose User needs to mention any one of the below purpose code:
P1 - Business for profit
P2 - Non-Profit
P3 - Personal
P4 - Educational
P5 - Governmental
Required String
nexusCatego
ry
User needs to mention any one of the below category code:
C11 - US Citizen
C12 - Permanent Resident
C21 - US Organization
C31/CC - Foreign Organization doing business in the US
C32/CC - Foreign Organization with US office
Required (^) String

7.2. IDN Supported Languages:
TLD Name Supported Language Language
Code
.com
Afrikaans AFR
Albanian ALB
Arabic ARA
Aragonese ARG
Armenian ARM
Assamese ASM
Asturian AST
Avestan AVE
Awadhi AWA
Azerbaijani AZE
Balinese BAN
Baluchi BAL
Basa BAS
Bashkir BAK
Basque BAQ
Belarusian BEL
Bengali BEN
Bhojpuri BHO
Bosnian BOS
Bulgarian BUL
Burmese BUR
Carib CAR
Catalan CAT
Chechen CHE
Chinese CHI
Chuvash CHV
Coptic COP
Corsican COS
Croatian SCR
Czech CZE
Danish DAN
Divehi DIV
Dogri DOI
Dutch DUT
English ENG
Estonian EST
Faroese FAO
Fijian FIJ
Finnish FIN
French FRE
Frisian FRY
Gaelic GLA
Georgian GEO
German GER
Gondi GON
Greek GRE
Gujarati GUJ
Hebrew HEB
Hindi HIN
Hungarian HUN
Icelandic ICE
Indic INC
Indonesian IND
Ingush INH
Irish GLE
Italian ITA
Japanese JPN
Javanese JAV
Kashmiri KAS
Kazakh KAZ
Khmer KHM
Kirghiz KIR
Korean KOR
Kurdish KUR
Latvian LAV
Lithuanian LIT
Luxembourgish LTZ
Macedonian MAC
Malay MAY
Malayalam MAL
Maltese MLT
Maori MAO
Moldavian MOL
Mongolian MON

Nepali NEP
Norwegian NOR
Oriya ORI
Ossetian OSS
Panjabi PAN
Persian PER
Polish POL
Portuguese POR
Pushto PUS
Rajasthani RAJ
Romanian RUM
Russian RUS
Samoan SMO
Sanskrit SAN
Sardinian SRD
Serbian SCC
Sindhi SND
Sinhalese SIN
Slovak SLO
Slovenian SLV
Somali SOM
Spanish SPA
Swahili SWA
Swedish SWE
Syriac SYR
Tajik TGK
Tamil TAM
Telugu TEL
Thai THA
Tibetan TIB
Turkish TUR
Ukrainian UKR
Urdu URD
Uzbek UZB
Vietnamese VIE
Welsh WEL
Yiddish YID
Languages

Language
Code TLD supported by languages^
Arabic ar .ae.org .press .fm .store
.art .protection .fun .tech
.baby .pw .host .theatre
.cam .radio.am .in.net .us.com
.ceo .radio.fm .tickets .us.org
.college .rent .monster .website
.com.fm .sa.com .net.fm .forum
.tickets .security .online .org.fm
.edu.fm .site .org.fm .space
.eu.com .best .storage
Belarusian be .eu.com .ooo
Bulgarian bg .cam .ooo
Bosnian bs .cam
Catalan ca .cam
Czech cs .cam .icu
Danish da .art .cam .ceo .eu.com
.qpon .tickets .icu .best
German de .audio .auto .best .cam
car .cars .ceo christmas
dealer diet eu.com flowers
.game .guitars .help .hosting
.ICU .inc .lol .london
.mom .pics .qpon .saarland
.tickets

Spanish es audio .auto .baby .best
.cam .car .cars .ceo
.christmas dealer diet .lat
.eu.com .flowers .game .guitars
.help .hosting .icu .inc
.lol .london .mom .ooo
.pics .qpon .tickets .uno
Estonian et .cam
Finnish fi .best .cam .ceo .qpon
Faroese fo .eu.com
French fr .cam .ceo .eu.com .icu
.london .ooo .cars .christmas
.audio .auto .best .car
.dealer .diet .flowers .game
.hosting .inc .lol .london
.qpon .tickets .guitars .help
.mom .pics
Hebrew he .art .baby .cam .college
.eu.com .fm .fun .host
.icu .in.net .monster .online
.ooo .press .protection .radio.am
.pw .radio.fm .rent .security
.site .space .storage .store
.tech .theatre .tickets .uno
.us.com .us.org .website .xyz
Hindi hi .ceo .best
Croatian hr .cam .eu.com .ooo
Hungarian hu .cam .ceo .qpon .best
Icelandic is .best .cam .ceo .icu
.qpon
Italian it .audio .auto .best .cam
.car .cars .dealer .diet
.ceo .christmas .flowers .game
.guitars .help .hosting .icu
.inc .lol .london .mom
.pics .qpon
Japanese ja .art .ceo .college .in.net
.fm .fun .host .jp.net
.jpn.com .online .ooo .press
.protection .pw .radio.am .radio.fm
.rent .security .storage .store
.site .space .tech .theatre
.us.com .us.org .website .xyz
.baby .cam .icu .monster
.auto .car .cars .dealer
.hair .makeup .quest .skin
.tickets .uno .inc .mom
.diet .flowers .pics .diet
.audio .beauty .best .christmas
.game .guitars .hosting .lol
.flowers
Korean ko .art .baby .cam .ceo
.edu.fm .fm .fun .hair
.in.net .uno .makeup .monster
.ooo .org.fm .press .protection
.college .com.fm .rent .security
.host .icu .store .tech
.net.fm .online .xyz .site
.pw .qpon .website .theatre
.quest .radio.am .radio.fm .beauty
.skin .space .storage .best
.tickets .uno
Luxembourgish lb .cam .icu

Lao lo .art .baby .college .fm
.fun .host .icu .website
.in.net .monster .online .ooo
.press .protection .pw .xyz
.radio.am .radio.fm .rent .security
.storage .store .tech .theatre
.site .space .tickets .uno
Lithuanian lt .cam .ceo .qpon .best
Latvian lv .cam .ceo .qpon .best
Macedonian mk .eu.com .ooo .cam
Dutch Flemish nl .cam .ceo .eu.com .qpon
.best

Norwegian no .cam .ceo .qpon .best
Polish pl .art .cam .ceo .eu.com
.ooo .qpon .tickets .best
Portuguese pt .cam .ceo .eu.com .icu
.audio .auto .best .car
.dealer .diet .flowers .game
.hosting .inc .lol .mom
.cars .christmas .pics .qpon
.guitars .help
Romanian ro .cam .icu
Russian ru .art .audio .auto .baby
.cam .car .cars .ceo
.beauty .best .game .guitars
.christmas .college .icu .ru.com
.diet .eu.com .flowers .security
.hair .help .hosting .skin
.lol .quest .rent .tickets
.makeup .mom .monster .xyz
.ooo .pics .storage .theatre
.protection .qpon
Slovak sk .cam
Slovene sl .cam
Albanian sq .cam
Serbian sr .cam .eu.com .ooo
Swedish sv .art .cam .ceo .eu.com
.ooo .qpon .tickets .best
Thai th .art .college .fm .host
.in.net .online .ooo .press
.protection .pw .radio.am .radio.fm
.rent .security .site .space
.storage .store .tech .theatre
.website .xyz .fun .tickets
.baby .cam .monster .uno
Turkish tr .cam .icu
Ukrainian uk .cam .eu.com .icu .ooo

Chinese zh .art .auto .baby .dealer
.car .cn.com .college .inc
.cars .ceo .icu .in.net
.fm .fun .hair .host
.makeup .monster .quest .rent
.uno .online .ooo .tickets
.press .protection .radio.am .radio.fm
.pw .qpon .storage .store
.security .site .skin .space
.tech .theatre .us.com .us.org
.website .xyz .diet .flowers
.audio .beauty .best .christmas
.game .guitars .help .hosting
.pics .lol .mom
Languages Language
Code
TLDs Supported
Arabic ar .biz .one شبكة
Belarusian be .design .gay .tube
Bosnian bs .ink .bank .com.co
Bulgarian bg .bid .insurance .net.co
Chinese zh .date .tattoo .nom.co
Danish da .download .adult .vip
Finnish fi .loan .boston .vodka
French fr .men .compare .wedding
German de .party .luxe .work
Hebrew he .stream .porn .yoga
Hindi hi .trade .select
Hungarian hu .win .sex
Icelandic is .faith .sucks
Italian it .racing .bible
Japanese ja .review .earth
Korean ko .science .osaka
Latvian lv .webcam .moe
Lithuanian lt .accountant .courses
Macedonian mk .cricket .health
Norwegian no .buzz .study
Polish pl .club .xxx
Portuguese pt .abogado .vu
Russian ru .beer .garden
Serbian sr .casa .horse
Spanish es .cooking .law
Swedish sv .fashion .miami
Thai th .fishing .rodeo
Ukrainian uk .fit .surf
8. Transfer
Transfer-in the domain name with us.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/TransferOrder?APIKey=<Your_API_Key>&Orde
rType=4&Websitename=domainexample.com&IsWhoisProtection=true&AuthCode=authcode&Id=
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
OrderType Value: 4 by default Required Integer
WebsiteName Domain Name that you need to register Required String
IsWhoisProtection Adds the privacy protection service for the
domain name
Required Boolean
Authcode EPP Domain Secret Required String
Id The customer ID for whom you wish to
register the domain name
Required Integer
Response:
responseMsg:
statusCode: If 200 then domain is transfer and other than 200 if domain is not transfer
message: Indicates the message and also reason if domain is not transfer
responseData: Null if domain is not transfer
responseMsg:
statusCode: 200 if domain transfer process is completed and it waiting for approval for losing registrar
and other than 200 if domain transfer is stall (locked or authcode invalid).
message: Indicates the message and reason for stall.
9. Cancel Transfer
Cancel the domain Transfer-In with us till the order is in stall status.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/CancelTransfer?APIKey=<Your_API_Key>&id=
00
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
Id The domain ID of the domain that you want
to cancel the transfer
Required Integer
Response:
responseMsg:
statusCode: 200 if transfer cancel is successful and other then 200 if transfer cancel failed.
message: Indicates the message and also reason if domain is not cancel.
responseData: Null
10. Validate a Transfer
Transfer a domain name with us.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/syncTransfer?APIKey=<Your_API_Key>&dom
ainName=domainexample.com
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainName Domain that you want to validate the transfer Required Integer
Response:
responseMsg:
statusCode: 200 if transfer for the domain was placed and 404 if transfer of domain was not placed.
message: Indicates the message.
responseData:
Status: Indicates Transfer Order status.
Reason: Reason if order is cancelled or stall
expiryDate: New expiry date of domain if order is completed.
11. Renew
Renew a domain name with us.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/RenewalOrder?APIKey=<Your_API_Key>&Ord
erType=2&Websitename=domainexample.com&IsWhoisProtection=true&Duration=00&Id=00&Expiryy
ear=
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
OrderType Represents the Order Type (2 for renewal) Required Integer
Duration No. of Years Required Integer
Websitename Domain name you want to renew Required String
Id Represents Customer ID Required Integer
IsWhoisProtection Adds the privacy protection service for the
domain name
Optional Boolean
Expiryyear Current expiry year of domain name. Optional String
Response:
responseMsg:
statusCode: 200 if domain is renewed and 404/400 if domain was not renew
message: Indicates the message.
responseData:
statusCode: Registry Status code.
Message: Message and reason.
expiryDate: New Expiry Date
domainName: Domain Name of renewed domain.
12. Getting Details of the Domain using ID
Getting Registered Domain details with us by ID
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/ViewDomainById?APIKey=<Your_API_Key>&i
d=
Request Parameters: Following table describes the request parameters of the pause object.

Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
Id ID of the Domain whose details need to be
fetched
Required Integer
Response:
responseMsg:
statusCode: 200 if domain is available in our records and 404 if not found
message: Indicates the message.
responseData:
domainNameId: domainNameId
websiteName: domain name
orderDate: Order date and time of domain in timestamp
creationDate: Registry Creation Date and time of domain in timestamp
lastUpdatedDate: Domain Modification Date and time of domain in timestamp
expirationDate: Registry Expiry Date and time of domain in timestamp
nameserver1: Nameservers 1
nameserver2: Nameservers 2
nameserver3: Nameservers 3
nameserver4: Nameservers 4
nameserver5: Nameservers 5
nameserver6: Nameservers 6
nameserver7: Nameservers 7
nameserver8: Nameservers 8
nameserver9: Nameservers 9
nameserver10: Nameservers 10
nameserver11: Nameservers 11
nameserver12: Nameservers 12
nameserver13: Nameservers 13
Status: Status under the System (currentstatus) - value will be Inactive, Active, Suspended, Pending
Delete Restorable, Deleted.
Authcode: Domain Secret key
isDomainLocked: Domain Lock Status
isThiefProtected: Thief Protection Status
isPrivacyProtection: Privacy Protection Status
isParked: domain Parked Status
customerId: Customer Id to which domain belongs.
registrantContactId: Registrant Contact
adminContactId: Admin Contact
technicalContactId: Technical Contact
billingContactId: Billing Contact
isRegistrantVerification: RAA Verification Status
registrantVerificationDate: RAA verification Date
websiteId: website Id for reference
dnszoneStatus: DNS is active or not
dnszoneId: DNS id

13. Getting Details of the Domain using Domain Name
Getting Registered Domain details with us by Domain Name.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/ViewDomain?APIKey=<Your_API_Key>&webs
iteName=example.com
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
websiteName Name of the domain whose details need to be
fetched
Required String
Response:
responseMsg:
statusCode: 200 if domain is available in our records and 404 if not found.
message: Indicates the message.
responseData:
domainNameId: domainNameId
websiteName: domain name
orderDate: Order date and time of domain in timestamp
creationDate: Registry Creation Date and time of domain in timestamp
lastUpdatedDate: Domain Modification Date and time of domain in timestamp
expirationDate: Registry Expiry Date and time of domain in timestamp
nameserver1: Nameservers 1
nameserver2: Nameservers 2
nameserver3: Nameservers 3
nameserver4: Nameservers 4
nameserver5: Nameservers 5
nameserver6: Nameservers 6
nameserver7: Nameservers 7
nameserver8: Nameservers 8
nameserver9: Nameservers 9
nameserver10: Nameservers 10
nameserver11: Nameservers 11
nameserver12: Nameservers 12
nameserver13: Nameservers 13
Status: Status under the System (currentstatus) - value will be Inactive, Active, Suspended, Pending
Delete Restorable, Deleted.
Authcode: Domain Secret key
isDomainLocked: Domain Lock Status
isThiefProtected: Thief Protection Status
isPrivacyProtection: Privacy Protection Status
isParked: domain Parked Status
customerId: Customer Id to which domain belongs.
registrantContactId: Registrant Contact
adminContactId: Admin Contact
technicalContactId: Technical Contact
billingContactId: Billing Contact
isRegistrantVerification: RAA Verification Status
registrantVerificationDate: RAA verification Date
websiteId: website Id for reference
dnszoneStatus: DNS is active or not
dnszoneId: DNS id
14. Search
Getting Registered Domain details with us by Domain Name.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/SearchDomainList?APIKey=<Your_API_Key>&
page=1&maxIndex=10&searchQuery=test&orderby=WebsiteName&orderType=asc&
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
Page Page for which details needs to be fetched Required Integer
maxIndex Max no. of records needs to be fetched Required Integer
orderby WebsiteName/ExpirationDate/CustomerId/Cr
eationDate
Optional String
orderType ASC/DESC Optional String
clientId Represents Customer ID Optional Integer
searchQuery Filter Optional String
Response:
totalCount: Total No of domain Count
record:
Array of domain with following details:
entryId: Domain Id
domainName: example.com
expirationDate: registry expiry date
creationDate: registry creation date
actionStatus: Domain status in system
resellerId: Reseller Id under which domain is registered.
customerId: Customer Id under which domain is registered.
15. Modify Nameserver of Domain
To update Domain Name Server in Registry
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/UpdateNameServer?APIKey=<Your_API_Key>
&domainNameId=1&websiteName=domainexample.com&nameServer1=nameServer1&nameServer2=
nameServer2&nameServer3=nameServer3&nameServer4=nameserver4
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainNameId Domain Name ID Required Integer
websiteName Domain Name Required String
nameServer1 New Name Servers 1 of the domain name Optional String
nameServer 2 New Name Servers 2 of the domain name Optional String
nameServer 3 New Name Servers 3 of the domain name Optional String
nameServer 4 New Name Servers 4 of the domain name Optional String
nameServer 5 New Name Servers 5 of the domain name Optional String
nameServer 6 New Name Servers 6 of the domain name Optional String
nameServer 7 New Name Servers 7 of the domain name Optional String
nameServer 8 New Name Servers 8 of the domain name Optional String
nameServer 9 New Name Servers 9 of the domain name Optional String
nameServer1 0 New Name Servers 1 0 of the domain name Optional String
nameServer1 1 New Name Servers 1 1 of the domain name Optional String
nameServer1 2 New Name Servers 1 2 of the domain name Optional String
nameServer1 3 New Name Servers 1 3 of the domain name Optional String
Response:
responseMsg:
statusCode: If 200 nameserver is modified and other then 200 if nameserver modification failed.
message: Indicates the message and reason if domain is not register.
responseData:
msg: Message from registry
msgCode: Message code from registry
16. Modify Authcode of Domain
To Modify Authcode of registered domain.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/updateAuthCode?APIKey=<Your_API_Key>&d
omainNameId=1&websiteName=domainexample.com&authCode=auth
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainNameId Domain Name ID Required Integer
websiteName Domain Name Required String
authCode New Authcode which you want to assign for
domain
Required String
Response:
responseMsg:
statusCode: If 200 Authcode is modified and other then 200 if Authcode modification failed.
message: Indicates the message and reason if domain is not register.
responseData:
msg: Message from registry
msgCode: Message code from registry
17. Manage Lock on Domain
To allow reseller to manage domain lock status.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/ManageDomainLock?APIKey=<Your_API_Key
>&domainNameId=1&websiteName=domainexample.com&isDomainLocked=true
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainNameId Domain Name ID Required Integer
websiteName Domain Name Required String
isDomainLocked Lock status for domain Required Boolean
Response:
responseMsg:
statusCode: 200 if manage lock action is successful and other than 200 if manage lock action failed.
message: Indicates the message
responseData:
statusCode: 200 if manage lock action is successful and other than 200 if manage lock action
failed.
message: Indicates the message
18. Manage Privacy on Domain
To allow Reseller to manage domain privacy protection
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/ManageDomainPrivacyProtection?APIKey=<Y
our_API_Key>&domainNameId=1&iswhoisprotected=true
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainNameId Domain Name ID Required Integer
isWhoIsProtected Privacy status for domain Required Boolean
Response:
responseMsg:
statusCode: 200 if manage privacy action is successful and other than 200 if manage privacy action
failed.
message: Indicates the message
responseData:
responseMsg:
statusCode: 200 if manage privacy action is successful and other than 200 if manage privacy action
failed.
message: Indicates the message
19. Manage Domain Suspend
To allow Resellers to manage domain suspend action
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/ManageDomainSuspend?APIKey=<Your_API_
Key>&domainNameId=1&websiteName=domainexample.com&isDomainSuspend=true
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainNameId Domain Name ID Required Integer
websiteName Domain Name Required String
isDomainSuspend Privacy status for domain Required Boolean
Response:
responseMsg:
statusCode: 200 if domain suspend action is successful and 400 if domain suspend action failed.
message: Indicates the message
responseData:
responseMsg:
statusCode: 200 if nameserver suspend action successful and 400 if nameserver suspend action failed.
message: Indicates the message
20. Manage Theft Protection on Domain
To allow Reseller to manage domain theft protection.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/ManageTheftProtection?APIKey=<Your_API_
Key>&domainNameId=1&websiteName=domainexample.com&isTheftProtection=true
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainNameId Domain Name ID Required Integer
websiteName Domain Name Required String
isTheftProtection Privacy status for domain Required Boolean
Response:
responseMsg:
statusCode: 200 if theft protection action on domain is successful and 400 if theft protection action on
domain is failed.
Message: Indicates the message
responseData:
responseMsg:
statusCode: 200 if theft protection action on domain is successful and 400 if theft protection action on
domain is failed.
Message: Indicates the message and reason if domain is not register
21. View Domain Secret Key
View Domain EPP secret key
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/ViewEPPCode?APIKey=<Your_API_Key>&dom
ainNameId=id
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainNameId Domain Name ID of the Domain whose EPP
code to be fetched
Required Integer
Response:
DomainSecretKey
22. Manage DNS Management
To Active DNS for Domain
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/ManageDNSRecords?APIKey=<Your_API_Key
>&WebsiteId=id
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
websiteId Refer website ID which we get in view domain
response
Required Integer
Response:
responseMsg:
statusCode: 200 if DNS management action on domain is successful and 400 if DNS management action
on domain is failed.
message: Indicates the message
responseData:
responseMsg:
statusCode: 200 if DNS management on domain is successful and 400 if theft DNS management on
domain is failed.
message: Indicates the message and reason if domain is not registered
23. Add DNS Record
To add DNS record for domain
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/AddDNSRecord?APIKey=<Your_API_Key>&DN
SZoneID=00&RecordName=host.example.com&RecordType=A&RecordValue=recordvalue&RecordPrio
rity=0&RecordTTL=43200
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
DNSZoneID DNS zone ID which we get in view domain
response
Required Integer
recordName Name for DNS entry Required String
recordType Type for DNS entry Required String
recordValue Value for DNS entry Optional String
recordTTL Time to live Optional Integer
recorePriority Set Priority Optional Integer
Response:
responseMsg:
statusCode: 200 if DNS record is added successfully domain is successful and 400 if DNS record is add
on domain is failed.
message: Indicates the message
responseData:
responseMsg:
statusCode: 200 if DNS record is added successfully domain is successful and 400 if DNS record is add
on domain is failed.
message: Indicates the message
24. Modify DNS Record
To Modify DNS record for Domain
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/ModifyDNSRecord?APIKey=<Your_API_Key>&
DNSZoneID=00&DNSZoneRecordID=00&RecordName=host.example.com&RecordType=A&RecordValu
e=recordvalue&RecordTTL=43200
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
DNSZoneID DNS zone ID which we get in view domain
response
Required Integer
DNSZoneRecord DNS zone record ID for which we want to
modity the domain
Required Integer
recordName Name for DNS entry Required String
recordType Type for DNS entry Required String
recordValue Value for DNS entry Optional String
recordTTL Time to live Optional Integer
Response:
responseMsg:
statusCode: 200 if DNS record is modified successfully and 400 if DNS record modification is failed.
message: Indicates the message
responseData:
statusCode: 200 if DNS record is added successfully and 400 if DNS record modification is failed.
message: Indicates the message
25. Delete DNS Record
To delete DNS record for Domain.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/DeleteDNSRecord?APIKey=<Your_API_Key>&
DNSZoneID=00&DNSZoneRecordID=00
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
DNSZoneID DNS zone ID which we get in view domain
response
Required Integer
DNSZoneRecordID DNSzone record ID for hich we want to
modify the domain
Required Integer
Response:
responseMsg:
statusCode: 200 if DNS record is deleted successfully and 400 if DNS record deletion is failed.
message: Indicates the message
responseData:
statusCode: 200 if DNS record is deleted successfully and 400 if DNS record deletion is failed.
26. View DNS Record
To view all DNS records.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/ViewDNSRecord?APIKey=<Your_API_Key>&W
ebsiteId=id
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
websiteID Refer website ID which we get in view domain
response
Required Integer
Response:
responseMsg:
statusCode: 200 if DNS record list is fetched successfully and 400 if DNS record list fetching is failed.
message: Indicates the message
responseData:
statusCode: 200 if DNS management on domain is failed and 400 if theft DNS management on domain
is failed.
message: Indicates the message and reason if domain is not registered.
27. Modifying Domain Contact
Modifies contacts of the specified register domain
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/updatecontact?APIKey=<Your_API_Key>&do
mainNameId=1&websiteName=domainexample.com&adminContactId=1234&billingContactId=4567&r
egistrantContactId=7891&technicalContactId=1235
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainNameID Domain Name ID Required Integer
websiteName Domain Name Required String
adminContactId The contact ID that you want to use as the
new admin contact
Required String
New Billing Contact The contact ID that you want to use as the
new billing contact
Required String
New Registrant The contact ID that you want to use as the Required String
Contact new registrant contact
New Technical
Contact
The contact ID that you want to use as the
new technical contact
Required String
Response:
responseMsg:
statusCode: 200 if domain contact updated successfully and 400 if domain contact updation is failed.
message: Indicates the message
responseData:
statusCode: 200 if domain contact updated successfully and 400 if domain contact updation is failed.
message: Indicates the message and reason if domain is not registered.
28. To move domain from one client to another
To move registered domain from one reseller to another
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/movedomain?APIKey=<Your_API_Key>&dom
ainNameId=1&customerId=123&userName=abc@example.com&isCustomerContact=1
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainNameID Domain Name ID which need to be moved Required Integer
customerID Client ID in domain which need to be moved Required Integer
userName User name of client in which domain needs to
be moved
Required String
isCustomerContact 1 indicates if we want to use default contact
of new client. 0 if we want to use existing
Contact
Required Boolean
Response:
responseMsg:
statusCode: 200 if domain is moved successfully and 400 if domain moving is failed.
message: Indicates the message
responseData:
responseMsg:
statusCode: 200 if domain is moved successfully and 400 if domain moving is failed.
message: Indicates the message and reason if domain is not registered.
29. Add SRV Record
To add SRV DNS record for Domain.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/addsrvrecord/?APIKey=<Your_API_Key>&DNS
ZoneID=2173&RecordName=host.test.com&RecordValue=tcp._scp.test.com&RecordPriority=0&Record
TTL=43200&RecordPort=1&RecordWeight=50
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
DNSZoneID DNS zone ID which we get in view domain
response
Required Integer
recordName Name for DNS entry Required String
recordType Type for DNS entry Required String
recordValue Value for DNS entry Optional String
recordTTL Time to live Optional Integer
recordPriority Set Priority Optional Integer
recordWeight Set Weight Required Integer
recordPort Set Port Required Integer
Response:
responseMsg:
statusCode: 200 if DNS record is added successfully domain is successful and 400 if DNS record is add
on domain is failed.
message: Indicates the message
responseData:
responseMsg:
statusCode: 200 if DNS record is added successfully domain is successful and 400 if DNS record is add
on domain is failed.
message: Indicates the message
30. Modify DNS Record for Domain
To Modify DNS record for Domain.
Method: GET
URL:
https://api.connectreseller.com/ConnectReseller/ESHOP/ModifyDNSRecord?APIKey=<Your_API_Key>&
DNSZoneID=00&DNSZoneRecordID=00&RecordName=host.example.com&RecordType=A&RecordValu
e=recordvalue&RecordTTL=43200&RecordPort=1&RecordWeight=50
Request Parameters: Following table describes the request parameters of the pause object.
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
DNSZoneID DNS zone ID which we get in view domain
response
Required Integer
DNSZoneRecordID DNSzone record ID for which we want to
modify the domain
Required Integer
recordName Name for DNS entry Required String
recordType Type for DNS entry Required String
recordValue Value for DNS entry Optional String
recordTTL Time to live Optional Integer
recordWeight Set Weight Required Integer
recordPort Set Port Required Integer
Response:
responseMsg:
statusCode: 200 if DNS record is modified successfully and 400 if DNS record modification is failed.
message: Indicates the message
responseData:
responseMsg:
statusCode: 200 if DNS record is added successfully and 400 if DNS record modification is failed.
message: Indicates the message
B - CONTACT
1. Add Contact
To add the Contact under Customer
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/AddRegistrantContact?APIKey=<<Your_API_KEY
>>&Name=johndeo&EmailAddress=johndeo@example com&CompanyName=example&Address=examp
leaddress&City=examplecity&StateName=examplestate&CountryName=CountryName&Zip=000&Phone
No_cc=00&PhoneNo=0000000000&Faxno_cc=00&FaxNo=00&Alternate_Phone_cc=00&Alternate_Phon
e=&Id=00
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
Name Name of the contact Required String
emailAddress Email Address of the contact Required String
companyName Company Name of the contact Required String
address Address of the contact Required String
city City of the contact Required String
stateName State of the contact Required String
CountryName Country of the contact Required String
zip Zip code of the contact Required String
phoneNo_cc Phone no. country code of the contact Required String
phoneNo Phone no. of the contact Required String
faxNo_cc Fax no. country code of the contact Optional String
faxNo Fax no. of the contact Optional String
alternatePhone_cc Alternate phone no. country code of the
contact
Optional String
alternatePhone Alternate phone no. of the contact Optional String
ID Client id for which you want modify the
details of Registrant Contact
Required Integer
Response:
responseMsg:
statusCode: 200 if contact is modified successfully and 400 if modifying contacts failed.
message: Indicates the message.
responseData:
statusCode: 200 if contact is modified successfully and 400 if modifying contacts failed.
message: Indicates the message.
2. Modify Contact
To modify the contact
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/ModifyRegistrantContact?APIKey=<Your_API_K
ey>&Name=johndeo&EmailAddress=johndeo@example com&CompanyName=example&Address=exam
pleaddress&City=examplecity&StateName=examplestate&CountryName=CountryName&Zip=000&Phon
eNo_cc=00&PhoneNo=0000000000&Faxno_cc=00&FaxNo=00&Alternate_Phone_cc=00&Alternate_Pho
ne=&RegistrantContactId=00
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
Name Name of the contact Required String
emailAddress Email Address of the contact Required String
companyName Company Name of the contact Required String
address Address of the contact Required String
city City of the contact Required String
stateName State of the contact Required String
CountryName Country of the contact Required String
zip Zip code of the contact Required String
phoneNo_cc Phone no. country code of the contact Required String
phoneNo Phone no. of the contact Required String
faxNo_cc Fax no. country code of the contact Optional String
faxNo Fax no. of the contact Optional String
alternatePhone_cc Alternate phone no. country code of the
contact
Optional String
alternatePhone Alternate phone no. of the contact Optional String
registrantContactId Client id for which you want add the contact Required Integer
Response:
responseMsg:
statusCode: 200 if contact is added successfully and 400 if adding contacts failed.
message: Indicates the message.
responseData:
statusCode: 200 if contact is added successfully and 400 if adding contacts failed.
message: Indicates the message.
3. View Contact
To view the contact details
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/ViewRegistrant?APIKey=<Your_API_Key>&Regis
trantContactId=00
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
registrantContactId Client id for which you want to view the Required Integer
contact
Response:
responseMsg:
statusCode: 200 if contact is available and 400 if contact is not available.
message: Indicates the message.
responseData:
Name: Name of the contact
emailaddress: Email Address of the contact
companyName: Company Name of the contact
address: Email Address of the contact
city: City of the contact
stateName: State of the contact
countryName: Country of the contact
zip: Zip code of the contact
phoneNo_cc: Phone no. country code of the contact
phoneNo: Phone no. of the contact
faxNo_cc: Fax no. country code of the contact
faxNo: Fax no. of the contact
alternatePhone_cc: Alternate phone no. country code of the contact
alternatePhone: Alternate phone no. of the contact
clientId: Customer ID to which contact belongs
4. To get Registrant list of specific client
To get registrant detailed list
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/registrantsearchlist?maxIndex=50&clientId=21
459&APIKey=<Your_API_Key>&page=1&searchQuery=p
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
page Page for which details need to be fetched Required Integer
maxIndex Max no. of records needs to be fetched Required Integer
clientId Customer ID Required Integer
searchQuery Filter Optional String
Response:
totalCount: Total no. of registrant counts
records:
Array of Registrant of client with following details
registrantContactId: Registrant Contact ID
emailaddress: Email Address
city: City
countryName: Country Name
stateName: State Name
actionStatus: Action Status
domainCount: Domain Count
Name: First Name of the contact
5. To Send RAA Verification mail
To send RAA verification mail to registrant contact.
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/resendRegistrantMail?contactId=00&APIKey=<
Your_API_Key>
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
contactId Registrant Contact ID to send email to Required Integer
Response:
{
"message": "Mail Send Successfully",
"id": 680,
"reason": null,
"statusCode": 200
}
6. Send KYC Email to registrant.
To send KYC email to registrant
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/sendKYCMail?registrantContactId= 00 &APIKey=
<Your_API_Key>
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
registrantContactId Registrants contact ID Required Integer
Response:
responseMsg:
{
"message": "Mail Send Successfully",
"id": 00 ,
"reason": null,
"statusCode": 200
}
C - CLIENT
1. Add Client
To add new customer
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/AddClient?APIKey=<Your_API_Key>&FirstName
=johndeo&UserName=johndeo@example com&Password=password&CompanyName=example&Addres
s1=exampleaddress&City=examplecity&StateName=examplestate&CountryName=CountryName&Zip=0
00&PhoneNo_cc=00&PhoneNo=0000000000&Faxno_cc=00&FaxNo=00&Alternate_Phone_cc=00&Alter
nate_Phone=&Id=00
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
Name Name of the client Required String
userName Email Address of the client Required String
password Password of the client Required String
companyName Company Name of the client Required String
Address1 Address of the client Required String
city City of the client Required String
stateName State of the client Required String
CountryName Country of the client Required String
zip Zip code of the client Required String
phoneNo_cc Phone no. country code of the client Required String
phoneNo Phone no. of the client Required String
faxNo_cc Fax no. country code of the client Optional String
faxNo Fax no. of the client Optional String
alternatePhone_cc Alternate phone no. country code of the
client
Optional String
alternatePhone Alternate phone no. of the client Optional String
Response:
responseMsg:
statusCode: 200 if client is added successfully and 400 if adding client failed.
message: Indicates the message.
responseData:
statusCode: 200 if client is added successfully and 400 if adding client failed.
message: Indicates the message.
2. Modify Client
To modify client
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/ModifyClient?APIKey=<Your_API_Key>&FirstNa
me=johndeo&UserName=johndeo@example com&Password=password&CompanyName=example&Add
ress=exampleaddress&City=examplecity&StateName=examplestate&CountryName=CountryName&Zip=
000&PhoneNo_cc=00&PhoneNo=0000000000&Faxno_cc=00&FaxNo=00&Alternate_Phone_cc=00&Alte
rnate_Phone= &Id=00
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
id Customer ID for which you want to modify
the details
Required Integer
firstName Name of the client Optional String
userName Email Address of the client Optional String
password Password of the client Optional String
companyName Company Name of the client Optional String
Address1 Address of the client Optional String
city City of the client Optional String
stateName State of the client Optional String
CountryName Country of the client Optional String
zip Zip code of the client Optional String
phoneNo_cc Phone no. country code of the client Optional String
phoneNo Phone no. of the client Optional String
faxNo_cc Fax no. country code of the client Optional String
faxNo Fax no. of the client Optional String
alternatePhone_cc Alternate phone no. country code of the
client
Optional String
alternatePhone Alternate phone no. of the client Optional String
Response:
responseMsg:
statusCode: 200 if client is modified successfully and 400 if modifying client failed.
message: Indicates the message.
responseData:
statusCode: 200 if client is modified successfully and 400 if modifying client failed.
message: Indicates the message.
3. View Client
To view client details
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/ViewClient?APIKey=<Your_API_Key>&UserNam
e=username@example com
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
userName User Name for which you want to view client
details
Required String
Response:
responseMsg:
statusCode: 200 if contact is available and 400 if contact is not available.
message: Indicates the message.
responseData:
clientId: ID of the client
resellerId: Reseller ID to which client belongs
firstName: Name of the client
emailaddress: Email Address of the client
companyName: Company Name of the client
password: Password of the client
address: Address of the client
city: City of the client
stateName: State of the client
countryName: Country of the client
zip: Zip code of the client
phoneNo_cc: Phone no. country code of the client
phoneNo: Phone no. of the client
faxNo_cc: Fax no. country code of the client
faxNo: Fax no. of the client
alternatePhone_cc: Alternate phone no. country code of the client
alternatePhone: Alternate phone no. of the client
4. Change the Client Password
To modify client’s password
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/ChangeClientPassword?APIKey=<Your_API_Key
>&UserName=johndeo@example com&OldPassword=password&NewPassword=example
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
userName Email Address of the client Required String
oldPassword Existing Passwword of the client Required String
password New Password of the client Required String
Response:
responseMsg:
statusCode: 200 if client’s password is changed successfully and 400 if client’s password change failed.
message: Indicates the message.
responseData:
statusCode: 200 if client’s password is changed successfully and 400 if client’s password change failed.
message: Indicates the message.
5. To Delete The Client
To delete the client
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/DeleteCustomer?APIKey=<Your_API_Key>&cus
tomerId=0000
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
customerId Client ID which you want to delete Required Integer
Response:
responseMsg:
statusCode: 200 if client is deleted successfully and 400 if client deletion failed.
message: Indicates the message.
responseData:
statusCode: 200 if client is deleted successfully and 400 if client deletion failed.
message: Indicates the message.
6. To Get A Client List
To get a client list
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/clientsearchlist?maxIndex=50&APIKey=<Your_
API_Key>&page=1
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
page Page for which details needs to be fetched Required Integer
maxIndex Max no. of records needs to be fetched Required Integer
searchQuery Filter Optional String
Response:
responseMsg:
totalCount: Total no. of domain count.
records: Array of clients with following details:
entryId: Client ID
customerName: Customer Name
emailId: Email Address
city: City
country: Country
status: Status
D - HOST
1. To Add Child Name Server
To add Child Name Server
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/AddChildNameServer?APIKey=<Your_API_Key>
&domainNameId=domainId&websiteName=example com&ipAddress=127 0 0 1&hostName=example
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainNameId Domain id of registered domain for which you
want to add child name server
Required Integer
websiteId Domain name of registered domain for which
you want to add child name server
Required String
hostName Child Name Server which you want to add Required String
ipAddress Ip address which you want to associate with
the Child Name Servers
Required String
Response:
responseMsg:
statusCode: 200 if child name server is added successfully and 400 if child name server addition failed.
message: Indicates the message.
responseData:
statusCode: 200 if child name server is added successfully and 400 if child name server addition failed.
message: Indicates the message.
2. Modify Name Server IP
To modify IP address of child name server.
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/ModifyChildNameServerIP?APIKey=<Your_API_
Key>&domainNameId=domainId&websiteName=example com&oldIpAddress=127 0 0 1&newIpAddress
=127 0 0 2&hostName=example
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainNameId Domain id of registered domain for which you
want to modify child name server
Required Integer
websiteId Domain name of registered domain for which
you want to modify child name server
Required String
hostName Child Name Server which you want to modify Required String
newIpAddress New Ip address which you want to associate
with the Child Name Servers
Required String
oldIpAddress Old IP Address Required String
Response:
responseMsg:
statusCode: 200 if child name server is added successfully and 400 if child name server addition failed.
message: Indicates the message.
responseData:
statusCode: 200 if child name server is added successfully and 400 if child name server addition failed.
message: Indicates the message.
3. To Modify Host Child Name Server
To Modify Child Name Server Host of the domain
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/ModifyChildNameServerHost?APIKey=<Your_A
PI_Key>&domainNameId=domainId&websiteName=example com&oldHostName=example&newHostNa
me=example
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainNameId Domain id of registered domain for which you
want to modify child name server
Required Integer
websiteId Domain name of registered domain for which
you want to modify child name server
Required String
oldHostName Old Child Name Server which you want to
modify
Required String
newHostName New Child Name Server Required String
Response:
responseMsg:
statusCode: 200 if child name server is modified successfully and 400 if child name server modification
failed.
message: Indicates the message.
responseData:
statusCode: 200 if child name server is modified successfully and 400 if child name server modification
failed.
message: Indicates the message.
4. To Delete Child Name Server
To Delete Child Name Server
Method : GET
URL :
https://api.connectreseller.com/ConnectReseller/ESHOP/DeleteChildNameServer?APIKey=<Your_API_K
ey>&domainNameId=domainId&websiteName=example.com&ipAddress=127.0.0.1&hostName=exampl
e
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainNameId Domain id of registered domain for which you
want to delete child name server
Required Integer
websiteId Domain name of registered domain for which
you want to delete child name server
Required String
hostName Child Name Server which you want to delete Required String
Response:
responseMsg:
statusCode: 200 if child name server is deleted successfully and 400 if child name server deletion failed.
message: Indicates the message.
responseData:
statusCode: 200 if child name server is deleted successfully and 400 if child name server deletion failed.
message: Indicates the message.
5. To Get Child Name Servers of a Domain
To get Child Name Servers of a domain.
Method : GET
URL :
https://api.connectreseller.com/ConnectReseller/ESHOP/getchildnameservers?APIKey=<Your_API_Key>
&id=domainId
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainNameId Domain id of registered domain for which you
want to get child name server list
Required Integer
Response:
responseMsg:
statusCode: 200 if child name server list is fetched successfully and 400 if child name server list fetching
is failed.
message: Indicates the message.
responseData:
[
{“hostname”:”ns1 example com”,”ipAddress”:”127 0 0 1”},
{“hostname”:”ns2 example com”,”ipAddress”:”127 0 0 1”}
{“hostname”:”ns3 example com”,”ipAddress”:”127 0 0 1”},
{“hostname”:”ns4 example com”,ipAddress”:”127 0 0 1”)
]
E - DOMAIN FORWARDING
1. To Set Domain Forwarding Details
To set domain forwarding registered with us.
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/SetDomainForwarding?APIKey=<Your_API_Key
>&domainNameId=domainId&websiteId=websiteId&isMasking=1&rewrite=http://www exampleple co
m
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainNameId Domain id of registered domain for which you
want to set Domain Forwarding
Required Integer
websiteId DomainWebsite id of registered domain for
which you want to set Domain Forwarding
(Refer website Id from view domain response)
Required String
isMasking Possible value is 1 or 0. If 1, visitors will see
the source URL and not the destination URL
Required Integer
rewrite URL where you want to forward your domain Required String
Response:
responseMsg:
statusCode: 200 if domain forwarding details are added successfully and 400 if domain forwarding
details addition failed.
message: Indicates the message.
responseData:
statusCode: 200 if domain forwarding details are added successfully and 400 if domain forwarding
details addition failed.
message: Indicates the message.
2. To Get Domain Forwarding Details
To get domain forwarding details of mentioned website.
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/GetDomainForwarding?APIKey=<Your_API_Key
>&websiteId=websiteId
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
websiteId Domain website ID of registered domain for
which you want to get domain forwarding
details. (Refer website ID from view domain
response)
Required Integer
Response:
responseMsg:
statusCode: 200 if domain forwarding details are fetched successfully and 400 if domain forwarding
details fetch failed.
message: Indicates the message.
responseData:
{
""websiteName": "example.com", "resellerId": id,
"isMasking": 1,
"rewrite":redirect URL if masking is disable or null, "proxyPass": redirect URL if masking is enable or
null,
}
3. To Update Domain Forwarding Details
To Update Domain Forwarding details of the domain.
Method : GET
URL :
https://api connectreseller com/ConnectReseller/ESHOP/updatedomainforwarding?APIKey=<Your_API_
Key>&domainNameId=domainId&websiteId=websiteId&isMasking=1&rewrite=http://www exampleple
com
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainNameId Domain ID of registered domain for which you
want to update Domain Forwarding
Required Integer
websiteId Domain website ID of registered domain for
which you want to update domain
Required String
forwarding. (Refer website ID from view
domain)
isMasking Possible value is 1 or 0. If 1, visitors will see
the source URL and not the destination URL
Required Integer
rewriteURL Where you want to forward your domain Required String
Response:
responseMsg:
statusCode: 200 if domain forwarding details are updated successfully and 400 if domain forwarding
details updation failed.
message: Indicates the message.
responseData:
statusCode: 200 if domain forwarding details are updated successfully and 400 if domain forwarding
details updation failed.
message: Indicates the message.
4. To Delete Domain Forwarding Details
To Delete Domain Forwarding details of the domain.
Method : GET
URL :
https://api.connectreseller.com/ConnectReseller/ESHOP/deletedomainforwarding?APIKey=<Your_API_
Key>&websiteId=websiteId&domainNameId=domainNameId
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
domainNameId Domain Name ID Required Integer
websiteId Domain website ID of registered domain for
which you want to delete domain forwarding
details. (Refer website ID from view domain
response)
Required Integer
Response:
responseMsg:
statusCode: 200 if domain forwarding details are deleted successfully and 400 if domain forwarding
deletion failed.
message: Indicates the message.
responseData:
statusCode: 200 if domain forwarding details are deleted successfully and 400 if domain forwarding
deletion failed.
message: Indicates the message.
F – MISCELLANEOUS
1. Check Reseller Available funds.
To get available funds data in resellers account
Method : GET
URL :
https://api.connectreseller.com/ConnectReseller/ESHOP/availablefund?APIKey=<YOUR_API_KEY>&rese
llerId=<YOUR_ID>
Request Parameters: Following table describes the request parameters of the pause object
Parameters Description Required/^
Optional
Type
APIKey Authentication Parameters Required String
resellerId Reseller ID Required Integer
Response:
{
"responseMsg": {
"message": "Available Fund",
"id": 0,
"statusCode": 0
},
"responseData": Available_Amount
}