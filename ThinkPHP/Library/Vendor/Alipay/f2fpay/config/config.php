<?php
		    $config = array (
		//签名方式,默认为RSA2(RSA2048)
		'sign_type' => "RSA2",

		//支付宝公钥
		'alipay_public_key' => "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAxCsWq0EPEKy12EguyaoWfeXmyUgn6ODssznBVZyKXf/Xts5CoRa2IO29s/eaSMBB8RXcAke459PdK5npQOiHQpuwxiA2Itzy2Z9rPCJpfd9+MdLsyY+IJDig7epFS11hopdNp8ZdRY6wFaB9dE3FdVSOwvUyMX8+f8BVBzfN65UZKe4SPRIQ6xQBPO6MM2llzQOUbQ4xo0qLWivHTS8uLaYds781NPPwbwxUbD9XkJDGItNafoyZbnL818/W+tNrWZKghxNXcAeyuHF0d9xzYweZksgaynoxHC7ezKTfQBk5sJ/YM4fqdx726YkF6yX9yicfullvV1vntpqyvvGUIwIDAQAB",

		//商户私钥
		'merchant_private_key' => "MIIEpAIBAAKCAQEAxCsWq0EPEKy12EguyaoWfeXmyUgn6ODssznBVZyKXf/Xts5CoRa2IO29s/eaSMBB8RXcAke459PdK5npQOiHQpuwxiA2Itzy2Z9rPCJpfd9+MdLsyY+IJDig7epFS11hopdNp8ZdRY6wFaB9dE3FdVSOwvUyMX8+f8BVBzfN65UZKe4SPRIQ6xQBPO6MM2llzQOUbQ4xo0qLWivHTS8uLaYds781NPPwbwxUbD9XkJDGItNafoyZbnL818/W+tNrWZKghxNXcAeyuHF0d9xzYweZksgaynoxHC7ezKTfQBk5sJ/YM4fqdx726YkF6yX9yicfullvV1vntpqyvvGUIwIDAQABAoIBAH2o0FuhUEIxMEc8beQ2tTOumnijZRVTR6zDOWpa3XO7WHY8iAfioYZUZGmtGUKzDUqe4xD+2p5+Y7XzYKx4h3SOgN1ZcvRALrxi13Fs5cCA8rh90bqH3AC/2a6tm/fb+JgLbe4kLklJTth7twFSdENliBGwuZdlWCbDkHQQNjNKbbrrgVjQHfJEOBhRsWVxJezrztz7RcuQRHg9o4mC6DVLDJ9si3VJzT4CpS7LOvIDTTGSgpFCmKE5yGE9/4eG0cPodd/tjaykoMN89539PdQkxC+qSIDKRUPutg1RnHft4cHgaybp2ON1YSMwonmJ/7DM78N2qS5NCF9Fq5xXQekCgYEA9gDsaPwebTmONtuNXoBkxEfgdNvUNhXvKksRWJuqJH7DFJiqeGAatyRvN4QSwEWLdZaUCkHu0389fcn5wb4RiG9dwzAtB/g42jFqkP1F2swaX0yorQHqt/CXS4AWbecJQy8TXyNNmBcKQ+nJn6fXTzDpvPSclHcfTkPIP2m7bT0CgYEAzCO/ovJKc7S6qgC6RzAm2cR/PCAgSXq+1+ZWZTXLcm7t85VD+sHzAk0F6wKsblTRmKUtgBVD0oJy3OPOeusRBK/uw2usRBmigTpSZLIX1P50b76fVuKQTWQ6/VZBGNm4y0Eib0YqI48jMMXXq+nGD6SxsIHaK5O5TjD4NDTR3N8CgYAWacEgU5AmHW9SmjBIIuSLaY0OuJSeFOOEc/BxpUUcLBxz/PDTJNZqRzyGz1ayA+QP45c7VASBan9cvZEu0LViO9tMFFCWAEyVvJjb+udpZt0kP6TCloEfHyF5tILWoo0afOiD64B/UeISi/Ndw3n/chJpr9OwRyYoCE7vUB/OdQKBgQDAu6OrwVu/oEt4RBWbLoAPrDCAYMh693OFPUgmaEK7uLXZ+vxinIjFjFjhB/YqeNQmbRTnT6xn7Jdrq3z/mj3IEh63zEmpRhaiMaEmxuovQ0pFUlD35BJfrxjupGfzgWOyKr3LFxCj3/lWjAPSoHVJwbGttvt3lxImG5/LEm9tOwKBgQDJDUQdbUbUhwA9AZ9Sd+dZFKWtpfdH41wR2hePtw7kQsZCsFTi1bYMSrjb2C6kBpxD8eS/KjBsS7+P3dxgkqb0sOKUCBTdCz5IqtcO7zjX6l8/m1zO5oYgkj9SjvLqobQP6BjS7rd6gPR+B5ith9zn8/TzMTSKREZmQbvaKWmJ2g==",

		//编码格式
		'charset' => "UTF-8",

		//支付宝网关
		'gatewayUrl' => "https://openapi.alipay.com/gateway.do",

		//应用ID
		'app_id' => "2017022105801515",

		//异步通知地址,只有扫码支付预下单可用
		'notify_url' => "http://www.baidu.com",

		//最大查询重试次数
		'MaxQueryRetry' => "10",

		//查询间隔
		'QueryDuration' => "3"
);