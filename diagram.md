## 3 types of inscription:
* invitation via code
* pay with MP or PayPal
* socio invitation

 code and socio has the type `Presencial`
and the other can choose between `Presencial`, `Virtual Full` and `Virtual Basic`

## we need to do:

* generate QR code when is a `Presencial` type
* Send email with the inscription.
* Generate a various entities:
* `Notificacion` entity, `Movimiento Charla` entity, `Eventos Usuarios` entity, `Congreso Inscripciones` entity

## The steps needs to be:

* Pay
* Generate QR (only `Presencial` and when its approved or completed the payment, or if it's a invitational code or socio)
* Send Email
* Send to the `Estas inscripto al evento` view