# congreso-refactor
Refactoring of the `Congreso` inscription + other things..

# Introduction
This project is created to refactor the current code of the inscription to the `Congreso` event. 
To buy a inscription you have to pay (obviously), or redeem a invitational code or just become partner. 
You can pay with MercadoPago or Paypal.
We need to generate the inscription with a QR code only for the inscription type `Presencial`.
The current DB is smth like this:

Events
id
type -> ('congreso',etc)
start_date
end_date

Plans
id
name ('Presencial', 'Virtual Basic', 'Virtual Full', etc)
price_arg_1
price_arg_2
price_usd_1
price_usd_1
end_date_1
end_date_2
start_date_1
start_date_2

Cupone 
id
limit (`tope`)
company_id
plan_id
number
due_date

Todo:
 We have three ways to get an inscription to the event:
   - Via invitational code
   - `Socio` inscription
   - Buying a inscription (as student, etc)

The correct way is: GenerateQr, do the payment, if this is fine, we registry the new inscription.

## Via Invitational Code
In the admin we can create the invitational code...
fill a form..
We need to verify if is valid and then redeem (new pay with total equals to 0, new inscription, etc)
@todo...

## `Socio` Inscription
Simple input where you put the socio_number or DNI/CUIT, validate if the `socio` exists and register the inscription if is a valid `socio`.
@todo ..

## Buying a inscription
Fill a form (generate new user or use a existing one)
Select the `Plan`(`Presencial`, `Virtual Basic`, `Virtual Full`) 
Select the pay method.
Select the type (With the ammount)
Summary view
Buy inscription with MP or Paypal
@todo ...

 
  
  
