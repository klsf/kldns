package controllers

import "kldns/services"

func providerResolver() services.DBProviderResolver {
	return services.DBProviderResolver{SecretKey: appSecret()}
}
