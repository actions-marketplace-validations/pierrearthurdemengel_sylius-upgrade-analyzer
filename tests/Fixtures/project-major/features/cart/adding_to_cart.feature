@cart
Feature: Adding products to the cart
    In order to buy products
    As a Customer
    I want to add products to my cart

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "PHP T-Shirt" priced at "$19.99"
        And the store has a product "Sylius Mug" priced at "$9.99"

    @ui
    Scenario: Adding a simple product to the cart
        Given I am a logged in customer
        When I add product "PHP T-Shirt" to the cart
        Then I should be on my cart summary page
        And I should see "PHP T-Shirt" in the cart
        And my cart total should be "$19.99"

    @ui
    Scenario: Adding multiple products to the cart
        Given I am a logged in customer
        When I add product "PHP T-Shirt" to the cart
        And I add product "Sylius Mug" to the cart
        Then my cart total should be "$29.98"

    @ui
    Scenario: Changing quantity of a cart item
        Given I am a logged in customer
        And I have product "PHP T-Shirt" in the cart
        When I change "PHP T-Shirt" quantity to 3
        Then my cart total should be "$59.97"

    @ui @javascript
    Scenario: Applying a coupon code
        Given I am a logged in customer
        And there is a promotion "Summer Sale" with coupon "SUMMER10"
        And this promotion gives "$10.00" discount
        When I add product "PHP T-Shirt" to the cart
        And I use coupon with code "SUMMER10"
        Then my cart total should be "$9.99"
