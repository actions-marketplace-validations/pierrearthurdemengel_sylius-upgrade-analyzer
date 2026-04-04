@checkout
Feature: Completing the checkout process
    In order to buy products
    As a Customer
    I want to complete the checkout process

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "PHP T-Shirt" priced at "$19.99"
        And the store ships everywhere for free
        And the store allows paying offline

    @ui
    Scenario: Completing checkout as a guest
        When I add product "PHP T-Shirt" to the cart
        And I specify the billing address as "Elm Street", "90210" "Los Angeles", "US"
        And I select "Free" shipping method
        And I select "Offline" payment method
        And I confirm my order
        Then I should see the thank you page

    @ui
    Scenario: Completing checkout with loyalty discount
        Given I am a logged in customer with "gold" loyalty tier
        And there is a promotion "Loyalty Gold" for "gold" tier customers
        When I add product "PHP T-Shirt" to the cart
        And I complete the checkout
        Then I should see the loyalty discount applied

    @ui @javascript
    Scenario: Completing checkout with Stripe payment
        When I add product "PHP T-Shirt" to the cart
        And I specify the billing address
        And I select "Free" shipping method
        And I select "Stripe" payment method
        And I fill in valid credit card details
        And I confirm my order
        Then I should see the thank you page
