<?php
return array(
    'API_SECRET_KEY'=>'www.tp-shop.cn', // app 调用的签名秘钥
    'ALIPAY_CONFIG'=>array( //支付宝配置
        'gatewayUrl'        =>  'https://openapi.alipay.com/gateway.do',//验证地址，不要动
        'appId'             =>  '2017102009412474',//应用ID
        //私钥
        'rsaPrivateKey'     =>  'MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCfTAI3YGCe20+Ao1cKNM7yo94GYp7LjCgOMAravTB2vyQFEPZpRmuz9OFeuxRs+mCrqvRrbzxnSZL8i+Zz7R6sOy44rorNkdPDQVX/hBwbDOtpGz2mJhN02oPxHpY7doY7XImvkyV165zKeEprtkxDAk2T1ulmjgtogfAH1zjgWgYUl1V5vJ7E0NqWKXyUaLTACTTvsVZzlqCYvESAq5h3E2U2m+lBpjLYAlI/Ss+3qU6hbTD2/dQR6ampabAR/Oqe6j+yC16/f8D69BaIbbFogxtg9s1imDAmo7c3J4t/SOoqOOWLd6cEyMaQgNQZhot/lJDcBwWwl7WxczoHRYUpAgMBAAECggEAWk47v2r6u3P22SVANcAERhfRyWrlPN1i49AmhyTTsa3gXKLmHn5WlnlPvmVuSe2TGh7bW4B/DMrv8g3ZgoS9a8RNCsMgWzO9iiai+yzIxikH19kilOtnAkrSm8HMRz+FD2gBgjB3/yaoBzw0bGW3TBGlKxedz47dNsza54cCbmSVnBEHKUkfqSQzJaTmFgNwT2l+o82PMjLJU9PNWpbOjloMJvlJVD5I/d5MrNPzNM3BweW8y1uetL2xvMWOysrk4IqoFEZXUAzk5fLuga5pab8abvrA3TkBONbQYY0LLJMoaevwT9XgvRglCjQt1hSedHMW6bt8hYdG4GI7OJX81QKBgQDPadKP72x30ROSsMTfQzXtX09QcaxJMHYkjo1Mw66eZBqlN6qvyY6y6f5HfohTtH6oB6NU3q4Gth0D/tmbT9d2mm5rkP6/69w3xMm8oNBC0roZe9fuO2FnzhFiYp7k5ecuvOvTg6wkpUiPkootwldC+kS+BHEWNJ0UqIKFQ4TYfwKBgQDEnLw/IitSvSsM3CcT9srXZivBqLtrtD2A3aELXAZm5oDsCE2oT85euUZDG9/YI5mfxlMnUvCDmBZ+1B+zhK3RJwPMzheyOHcJuBeszJrlSQ6GD0gle+JriHPzMLtN3664DNNlzklBblRwYwtlhUIB/Wh+53jlwUG6gtvUsjcOVwKBgQDJ0wQkMOqQEG8GT2HSihkAeLy531KxCSn82oWeC89vqqLO38MEOJHVgKGAuhw3ryuLn5sMK36VY0IKsELYwTE85HrLypRI9l4cGi3fkW/1hg22XG66DyfyFW+PF73bp+Zz8TtcXwTzx606n3I7op68usCHUdUksV+kSXBv8kpjSwKBgE2pV5Zcid5ZyIqA35K8Ni1VN018t+N4Q58GbFmPVRwKSrxxHzOvqmWyK3XQqd+3WDRLY4cx9L0WMzSP16HSc9Ic5hxc//Eu6p5VMEzaWQjejbUYjjf4Mlylfp9+DU5aX6plro8VJ8yHpyTpipPkSkl87mzKQ/AFIVBp5imi56prAoGBAI/WsPA2K9ZFONtBevQNVNlAgmcNf5Iu+oO18dohDjkR+UDVXgg56TB6QumpbL1TajfAHUgQeAT4FI/f/zwBjP3Fdc/RfuTy3yVPAtOF9bE+OgGiKG2H8S1n+5vB0nW1wm928IPbklQl0QKYxzFPQBWu/ma22JvLYm6HhDYKYTE5',
        'format'            =>  'json',//数据类型
        'postCharset'       =>  'UTF-8',//编码
        'signType'          =>  'RSA2',//验证方式
        //支付宝公钥  不是 应用公钥
        //'alipayrsaPublicKey'=>  'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAn0wCN2BgnttPgKNXCjTO8qPeBmKey4woDjAK2r0wdr8kBRD2aUZrs/ThXrsUbPpgq6r0a288Z0mS/Ivmc+0erDsuOK6KzZHTw0FV/4QcGwzraRs9piYTdNqD8R6WO3aGO1yJr5MldeucynhKa7ZMQwJNk9bpZo4LaIHwB9c44FoGFJdVebyexNDalil8lGi0wAk077FWc5agmLxEgKuYdxNlNpvpQaYy2AJSP0rPt6lOoW0w9v3UEempqWmwEfzqnuo/sgtev3/A+vQWiG2xaIMbYPbNYpgwJqO3NyeLf0jqKjjli3enBMjGkIDUGYaLf5SQ3AcFsJe1sXM6B0WFKQIDAQAB',
        'alipayrsaPublicKey'=>  'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAivI2G2Vb8G1dpCKVC21tRfy8eVoXMqMi1iEuw4mEAC4B2DBkpL7ErTXh4vaaqBvzmDOBDDGhujB0moPeDEuV9Q29VbA+NH32K+Em0GDxn21mUdg6tNKnovEKW3C0TA84ZF06YneXz0PeZixJizOwMp61S0tjCIVXFb4PfAx51KH8DP53URDofTS5wcd0s+x7byZ+nwrF/Nez5IIJYbKdfynQNxU0Yx+1vBDdHmVUDW6qxwsDiblZYX2dlH4i/pmwk0Vjhxp+/RCdfsuNrshMDbfU5lHSPSKos2GzwG/F9ba0UWua/O1Hf85m+EWOiPs/3kwdn9C/stFKUF7XBgRwQQIDAQAB',
        //回调地址
        'NotifyUrl'      =>  'http://'.$_SERVER['HTTP_HOST'].'/index.php/'.'Api/Payment/alipay_notify'
    ),
);