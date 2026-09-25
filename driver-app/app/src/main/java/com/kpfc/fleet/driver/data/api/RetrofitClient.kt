package com.kpfc.fleet.driver.data.api

import okhttp3.Cookie
import okhttp3.CookieJar
import okhttp3.HttpUrl
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.ConcurrentHashMap
import java.util.concurrent.TimeUnit

object RetrofitClient {

    // Default base URL: Staging environment
    @Volatile
    var baseUrl: String = "https://admin-staging.kpfcbuilders.com/"
        private set

    // Thread-safe in-memory session cookie store to maintain Laravel session cookies
    private val cookieStore = ConcurrentHashMap<String, MutableList<Cookie>>()

    private val cookieJar = object : CookieJar {
        override fun saveFromResponse(url: HttpUrl, cookies: List<Cookie>) {
            val existing = cookieStore.getOrPut(url.host) { mutableListOf() }
            synchronized(existing) {
                cookies.forEach { newCookie ->
                    existing.removeAll { it.name == newCookie.name }
                    existing.add(newCookie)
                }
            }
        }

        override fun loadForRequest(url: HttpUrl): List<Cookie> {
            val cookies = cookieStore[url.host] ?: return emptyList()
            synchronized(cookies) {
                return cookies.toList()
            }
        }
    }

    private val loggingInterceptor = HttpLoggingInterceptor().apply {
        level = HttpLoggingInterceptor.Level.BODY
    }

    private val okHttpClient: OkHttpClient = OkHttpClient.Builder()
        .cookieJar(cookieJar)
        .addInterceptor(loggingInterceptor)
        .addInterceptor { chain ->
            val request = chain.request().newBuilder()
                .header("Accept", "application/json")
                .header("Content-Type", "application/json")
                .build()
            chain.proceed(request)
        }
        .connectTimeout(30, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .writeTimeout(30, TimeUnit.SECONDS)
        .build()

    @Volatile
    private var retrofit: Retrofit = buildRetrofit(baseUrl)

    @Volatile
    var apiService: DriverApiService = retrofit.create(DriverApiService::class.java)
        private set

    private fun buildRetrofit(url: String): Retrofit {
        val normalizedUrl = if (url.endsWith("/")) url else "$url/"
        return Retrofit.Builder()
            .baseUrl(normalizedUrl)
            .client(okHttpClient)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
    }

    fun setCustomBaseUrl(newUrl: String) {
        val normalized = if (newUrl.endsWith("/")) newUrl else "$newUrl/"
        baseUrl = normalized
        retrofit = buildRetrofit(normalized)
        apiService = retrofit.create(DriverApiService::class.java)
    }

    fun clearSession() {
        cookieStore.clear()
    }
}
